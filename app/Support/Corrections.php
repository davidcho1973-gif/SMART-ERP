<?php

namespace App\Support;

use App\Models\AttendanceCorrection;
use App\Models\AuditLog;
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
        $ids = Employee::whereIn('access', ['admin', 'owner', 'hr_admin'])->where('emp', 'active')->pluck('id')->all();
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

    /**
     * May this actor approve/reject the request? The requester never decides their
     * own request. Global deciders (owner/hr_admin) act org-wide; a site manager
     * acts when the worker is on one of their sites; a crew lead when the request
     * snapshotted them as the responsible lead.
     */
    public static function canDecide(AttendanceCorrection $c, ?int $actorEmpId, bool $global, ?array $siteScope = null): bool
    {
        if (! $c->isPending() || ! $actorEmpId) {
            return false;
        }
        if ($actorEmpId === (int) $c->employee_id) {
            return false; // no self-approval
        }
        if ($global) {
            return true;
        }
        if ($c->lead_id !== null && $actorEmpId === (int) $c->lead_id) {
            return true;
        }
        if ($siteScope) {
            $siteId = Employee::find($c->employee_id)?->site_id;

            return $siteId !== null && in_array($siteId, $siteScope, true);
        }

        return false;
    }

    /**
     * Apply an approved request to the punch record and close it out.
     *
     * The approver may pass adjusted in/out minutes to override what the worker asked
     * for (approve-with-adjustment); the applied values are recorded for the audit trail.
     * Pass the sentinel false to "leave unset" vs null which means "clear the time".
     */
    public static function approve(AttendanceCorrection $c, int $actorEmpId, int|null|false $inMin = false, int|null|false $outMin = false): void
    {
        if (! $c->isPending()) {
            return; // idempotent — a concurrent decision already closed it
        }

        $applIn = $applOut = null;
        if ($c->type === 'delete') {
            Punch::where('employee_id', $c->employee_id)->where('work_date', $c->work_date)->delete();
        } else {
            // approver's edit wins when provided; otherwise apply exactly what was requested
            $applIn = $inMin === false ? $c->req_in_min : $inMin;
            $applOut = $outMin === false ? $c->req_out_min : $outMin;
            $p = Punch::firstOrNew(['employee_id' => $c->employee_id, 'work_date' => $c->work_date]);
            $p->in_min = $applIn;
            $p->out_min = $applOut;
            $p->source = 'manual';
            // an approved correction supersedes any earlier lead adjustment —
            // a stale adj_* would silently override the corrected times in settle()
            $p->adj_in_min = null;
            $p->adj_out_min = null;
            $p->adj_reason = null;
            $p->adj_by = null;
            if ($p->team_id === null) {
                $p->team_id = Employee::find($c->employee_id)?->team_id;
            }
            if ($p->shift_in_snap === null) {
                $p->stampShiftSnap();
            }
            $p->save();
        }

        $c->update([
            'status' => 'approved',
            'decided_by' => $actorEmpId,
            'decided_at' => now(),
            'appl_in_min' => $applIn,
            'appl_out_min' => $applOut,
        ]);
        self::auditDecision($c, $actorEmpId, 'correction.approve');
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
        self::auditDecision($c, $actorEmpId, 'correction.reject');
    }

    /** Correction decisions join the org-wide audit stream (Phase 4). */
    protected static function auditDecision(AttendanceCorrection $c, int $actorEmpId, string $action): void
    {
        $actor = Employee::find($actorEmpId);
        $worker = Employee::find($c->employee_id);
        AuditLog::create([
            'actor_id' => $actorEmpId,
            'actor_name' => $actor ? trim($actor->first.' '.$actor->last) : '#'.$actorEmpId,
            'action' => $action,
            'target' => ($worker ? trim($worker->first.' '.$worker->last) : '#'.$c->employee_id).' · '.$c->work_date,
            'detail' => $c->decision_note,
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
