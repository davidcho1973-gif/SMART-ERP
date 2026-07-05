<?php

namespace App\Support;

use App\Models\AttendanceCorrection;
use App\Models\Channel;
use App\Models\Employee;
use App\Models\Message;
use App\Models\Payment;
use App\Models\Punch;
use App\Models\Team;
use Illuminate\Support\Carbon;

/**
 * Attendance-correction requests: a worker proposes corrected times for a work date,
 * the responsible team lead or an HR admin approves (or rejects), and an approval is
 * applied straight to the punch record. The request row is also the audit trail.
 *
 * Safeguards baked in (see the design audit):
 *  - the assigned company/crew/lead are snapshotted so history stays correct after moves
 *  - a date inside an already-paid payroll period is off-limits to workers (admin-only)
 *  - the requester can never approve their own request; no lead → HR admin only
 *  - decisions only apply to a still-pending request (idempotent under double-clicks)
 */
class Corrections
{
    /** How far back a worker may reach when filing a correction. */
    public const WINDOW_DAYS = 45;

    /** The approver-only correction room (created on first use). */
    public static function channel(): Channel
    {
        return Channel::firstOrCreate(['type' => 'correction'], ['name' => '출퇴근 정정요청']);
    }

    /** Is this work date inside a payroll period that has already been paid out? */
    public static function isPaidPeriod(int $employeeId, string $workDate): bool
    {
        return Payment::where('employee_id', $employeeId)
            ->where('period_start', '<=', $workDate)
            ->where('period_end', '>=', $workDate)
            ->exists();
    }

    /** Freeze who the worker was assigned to (company / crew / lead) for the work date. */
    public static function snapshot(Employee $worker): array
    {
        $team = $worker->team_id ? Team::find($worker->team_id) : null;

        return [
            'company_id' => $team?->company_id ?? $worker->company_id,
            'team_id' => $worker->team_id,
            'lead_id' => $team?->lead,
        ];
    }

    /** Employee ids that may act on a request: every active HR admin + the snapshot lead. */
    public static function approverEmployeeIds(AttendanceCorrection $c): array
    {
        $ids = Employee::where('access', 'admin')->where('emp', 'active')->pluck('id')->all();
        if ($c->lead_id) {
            $ids[] = (int) $c->lead_id;
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /** Create a pending request and light up the approvers' bell in the correction room. */
    public static function submit(Employee $worker, string $workDate, string $type, ?int $reqIn, ?int $reqOut, string $reason): AttendanceCorrection
    {
        $snap = self::snapshot($worker);
        $punch = Punch::where('employee_id', $worker->id)->where('work_date', $workDate)->first();

        $c = AttendanceCorrection::create([
            'employee_id' => $worker->id,
            'work_date' => $workDate,
            'type' => $type === 'delete' ? 'delete' : 'set',
            'req_in_min' => $type === 'delete' ? null : $reqIn,
            'req_out_min' => $type === 'delete' ? null : $reqOut,
            'orig_in_min' => $punch?->in_min,
            'orig_out_min' => $punch?->out_min,
            'reason' => mb_substr(trim($reason), 0, 500),
            'company_id' => $snap['company_id'],
            'team_id' => $snap['team_id'],
            'lead_id' => $snap['lead_id'],
            'status' => 'pending',
        ]);

        $ch = self::channel();
        $c->update(['channel_id' => $ch->id]);
        foreach (self::approverEmployeeIds($c) as $id) {
            $ch->members()->firstOrCreate(['employee_id' => $id]);
        }
        // a message drives the existing unread bell for the approver members
        Message::create([
            'channel_id' => $ch->id,
            'sender_id' => $worker->id,
            'body' => '정정요청 · '.$workDate,
        ]);

        return $c;
    }

    /** May this actor approve/reject the request? (requester excluded, lead-or-admin only) */
    public static function canDecide(AttendanceCorrection $c, ?int $actorEmpId, bool $isAdmin): bool
    {
        if (! $c->isPending() || ! $actorEmpId) {
            return false;
        }
        if ($actorEmpId === (int) $c->employee_id) {
            return false; // no self-approval
        }
        if ($isAdmin) {
            return true;
        }

        return $c->lead_id !== null && $actorEmpId === (int) $c->lead_id;
    }

    /** Apply an approved request to the punch record and close it out. */
    public static function approve(AttendanceCorrection $c, int $actorEmpId): void
    {
        if (! $c->isPending()) {
            return; // idempotent — a concurrent decision already closed it
        }

        if ($c->type === 'delete') {
            Punch::where('employee_id', $c->employee_id)->where('work_date', $c->work_date)->delete();
        } else {
            $p = Punch::firstOrNew(['employee_id' => $c->employee_id, 'work_date' => $c->work_date]);
            $p->in_min = $c->req_in_min;
            $p->out_min = $c->req_out_min;
            $p->source = 'manual';
            $p->save();
        }

        $c->update(['status' => 'approved', 'decided_by' => $actorEmpId, 'decided_at' => now()]);
        self::syncEmployeeStatus($c);
    }

    /** Reject a request with a note. */
    public static function reject(AttendanceCorrection $c, int $actorEmpId, string $note): void
    {
        if (! $c->isPending()) {
            return;
        }
        $c->update([
            'status' => 'rejected',
            'decided_by' => $actorEmpId,
            'decided_at' => now(),
            'decision_note' => mb_substr(trim($note), 0, 300) ?: null,
        ]);
    }

    /** When a correction lands on *today*, keep the roster row's live status in sync. */
    protected static function syncEmployeeStatus(AttendanceCorrection $c): void
    {
        if ($c->work_date !== Carbon::now()->format('Y-m-d')) {
            return;
        }
        $e = Employee::find($c->employee_id);
        if (! $e) {
            return;
        }
        $p = Punch::where('employee_id', $c->employee_id)->where('work_date', $c->work_date)->first();
        if (! $p || $p->in_min === null) {
            $e->update(['status' => 'off', 'in_t' => '—', 'out_t' => '—']);
        } elseif ($p->out_min === null) {
            $e->update(['status' => 'present', 'in_t' => Shift::fmtMin($p->in_min), 'out_t' => '—']);
        } else {
            $e->update(['status' => 'off', 'in_t' => Shift::fmtMin($p->in_min), 'out_t' => Shift::fmtMin($p->out_min)]);
        }
    }
}
