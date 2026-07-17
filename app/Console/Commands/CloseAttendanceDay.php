<?php

namespace App\Console\Commands;

use App\Models\Absence;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\Punch;
use App\Models\Team;
use App\Support\Shift;
use App\Support\WorkerStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * End-of-day close for a finished work day. Two jobs:
 *
 *   1. No-shows — any active worker who was SCHEDULED but never clocked in (and
 *      has no approved leave / existing absence) is recorded as an unexcused
 *      no-show (무단결근), so the lead's "미출근" becomes a countable 무단결근.
 *
 *   2. Missing clock-outs — any punch that clocked IN but never clocked OUT is
 *      auto-closed to the scheduled shift end (from the frozen shift snapshot),
 *      flagged out_auto = true. Without this the day is dropped from payroll
 *      entirely (the engine only counts in+out punches) — the worker would be
 *      unpaid for a day they worked. Capped at the scheduled end so a forgotten
 *      punch never over-pays; a lead can still correct it to the real time.
 *
 *   php artisan attendance:close-day            # closes yesterday
 *   php artisan attendance:close-day 2026-07-08 # closes a specific day
 */
class CloseAttendanceDay extends Command
{
    protected $signature = 'attendance:close-day {date? : YYYY-MM-DD, defaults to yesterday}';

    protected $description = 'Record no-shows (무단결근) and auto-close missing clock-outs for a finished work day';

    public function handle(): int
    {
        $ymd = $this->argument('date') ?: Carbon::now()->subDay()->format('Y-m-d');
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) {
            $this->error('Invalid date: '.$ymd);

            return self::FAILURE;
        }

        $teams = Team::all()->keyBy('id');
        $punched = Punch::where('work_date', $ymd)->whereNotNull('in_min')->pluck('employee_id')->flip();
        $onLeave = Leave::where('status', 'approved')
            ->where('start_date', '<=', $ymd)->where('end_date', '>=', $ymd)
            ->pluck('employee_id')->flip();
        $already = Absence::where('work_date', $ymd)->pluck('employee_id')->flip();

        $created = 0;
        foreach (Employee::where('emp', 'active')->get() as $e) {
            if ($punched->has($e->id) || $onLeave->has($e->id) || $already->has($e->id)) {
                continue;
            }
            if (! WorkerStatus::scheduledDay($teams->get($e->team_id), $ymd)) {
                continue;   // weekend / non-shift day — not a no-show
            }
            Absence::create([
                'employee_id' => $e->id, 'work_date' => $ymd,
                'kind' => 'unexcused', 'source' => 'auto',
            ]);
            $created++;
        }

        // ---- auto-close missing clock-outs: clocked in, never clocked out ----
        $saturday = Carbon::parse($ymd)->isSaturday();
        $open = Punch::where('work_date', $ymd)
            ->whereNotNull('in_min')->whereNull('out_min')->get();
        $closed = 0;
        $skipped = 0;
        foreach ($open as $p) {
            // scheduled end: prefer the frozen snapshot, else recompute from the crew's shift
            $out = $p->shift_out_snap;
            if ($out === null) {
                $sched = $teams->get($p->team_id)?->shiftFor($saturday);
                $out = $sched[1] ?? null;
            }
            // can't safely close without a schedule, and never close backwards
            // (a null or crosses-midnight end is left for a lead to enter by hand)
            if ($out === null || $out <= $p->in_min) {
                $skipped++;

                continue;
            }
            $p->out_min = $out;
            $p->out_auto = true;
            $p->save();

            $emp = Employee::find($p->employee_id);
            AuditLog::create([
                'actor_id' => null,
                'actor_name' => 'system',
                'action' => 'attendance.auto_out',
                'target' => $emp ? trim($emp->first.' '.$emp->last).' (#'.$emp->id.')' : ('punch #'.$p->id),
                'detail' => $ymd.' · '.Shift::fmtMin($p->in_min).' → '.Shift::fmtMin($out).' · auto (scheduled end)',
            ]);
            $closed++;
        }

        $this->info("Closed {$ymd}: {$created} unexcused no-show(s), {$closed} missing clock-out(s) auto-closed"
            .($skipped ? ", {$skipped} open punch(es) left for manual review" : '').'.');

        return self::SUCCESS;
    }
}
