<?php

namespace App\Console\Commands;

use App\Models\Absence;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\Punch;
use App\Models\Team;
use App\Support\WorkerStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * End-of-day close: any active worker who was SCHEDULED that day but never
 * clocked in — and has no approved leave and no existing absence record — is
 * recorded as an unexcused no-show (무단결근). Run just after midnight for the
 * day that just ended, so the lead's "미출근" becomes a countable 무단결근.
 *
 *   php artisan attendance:close-day            # closes yesterday
 *   php artisan attendance:close-day 2026-07-08 # closes a specific day
 */
class CloseAttendanceDay extends Command
{
    protected $signature = 'attendance:close-day {date? : YYYY-MM-DD, defaults to yesterday}';

    protected $description = 'Record unexcused no-shows (무단결근) for a finished work day';

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

        $this->info("Closed {$ymd}: {$created} unexcused no-show(s) recorded.");

        return self::SUCCESS;
    }
}
