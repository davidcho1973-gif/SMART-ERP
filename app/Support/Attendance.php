<?php

namespace App\Support;

use App\Models\Punch;
use App\Models\Team;
use Illuminate\Support\Carbon;

/**
 * Settles one day's punch into PAID time, in one place shared by the timesheet,
 * the payroll screen and the Excel export.
 *
 * Rules (team-lead configurable shift + ±30 min grace):
 *   1. Each crew has a scheduled shift (e.g. 5:00–2:00). A punch within 30 min
 *      of the shift start/end snaps to the shift, so slightly-early/late punches
 *      still pay the full guaranteed shift (8h after the 1h lunch).
 *   2. Overtime is NOT automatic. Staying past the shift end is capped at the
 *      shift end — the paid time only extends when a team lead approves it
 *      (adj_out_min on the punch).
 *   3. Leaving more than 30 min early pays the actual worked time; a lead can
 *      restore it with an adjustment if there's a reason.
 *
 * Legacy fallback: a crew with no configured shift keeps the old behavior
 * (schedule guessed from the punch-in time, symmetric ±30 snap, no OT cap), so
 * nothing changes for a team until its lead sets a shift.
 */
class Attendance
{
    /**
     * Per-request team cache for the live-shift fallback: settling a whole
     * roster/period would otherwise Team::find() once per punch. Callers that
     * already hold the teams (ViewModel, Timesheet, export) warm it; entries
     * are overwritten on every warm so staleness is bounded to one request.
     *
     * @var array<string,?Team>
     */
    protected static array $teamCache = [];

    /** @param  iterable<Team>  $teams */
    public static function warmTeams(iterable $teams): void
    {
        self::$teamCache = [];   // full refresh — also drops deleted teams under long-running workers
        foreach ($teams as $t) {
            self::$teamCache[$t->id] = $t;
        }
    }

    protected static function team(?string $id): ?Team
    {
        if ($id === null) {
            return null;
        }
        if (! array_key_exists($id, self::$teamCache)) {
            self::$teamCache[$id] = Team::find($id);
        }

        return self::$teamCache[$id];
    }

    /**
     * @return array{
     *   paidIn:int, paidOut:int, paidInFmt:string, paidOutFmt:string,
     *   paid:float, shiftIn:?int, shiftOut:?int, source:string, adjusted:bool
     * }|null  null when the punch has no in/out to settle
     */
    public static function settle(Punch $p): ?array
    {
        if ($p->in_min === null || $p->out_min === null) {
            return null;
        }
        $actIn = (int) $p->in_min;
        $actOut = (int) $p->out_min;
        $saturday = Carbon::parse($p->work_date)->isSaturday();

        // schedule: prefer the shift FROZEN on the punch at clock-in — editing a
        // team's shift later must never rewrite already-earned paid hours. Only
        // punches from before the snapshot existed fall back to the live team.
        if ($p->shift_in_snap !== null && $p->shift_out_snap !== null) {
            $shift = [(int) $p->shift_in_snap, (int) $p->shift_out_snap];
        } else {
            $shift = self::team($p->team_id)?->shiftFor($saturday);
        }

        if ($shift !== null) {
            [$shiftIn, $shiftOut] = $shift;
            $source = 'team';
            // paid-in: early arrival capped at shift start; up to 30 min late still
            // snaps up to the start; more than 30 min late is docked to actual
            $paidIn = $actIn <= $shiftIn + Shift::GRACE_MIN ? $shiftIn : $actIn;
            // paid-out: within 30 min of end (or later) → capped at shift end (no
            // automatic OT); leaving >30 min early → actual (early leave)
            $paidOut = $actOut >= $shiftOut - Shift::GRACE_MIN ? $shiftOut : $actOut;
        } else {
            // legacy: guess the schedule and snap symmetrically (unchanged behavior)
            [$shiftIn, $shiftOut] = Payroll::scheduleFor($actIn, $saturday);
            $source = 'guess';
            $paidIn = Shift::snap($actIn, $shiftIn);
            $paidOut = Shift::snap($actOut, $shiftOut);
        }

        // team-lead override wins on either leg (approves OT / restores early-leave)
        $adjusted = $p->isAdjusted();
        if ($p->adj_in_min !== null) {
            $paidIn = (int) $p->adj_in_min;
        }
        if ($p->adj_out_min !== null) {
            $paidOut = (int) $p->adj_out_min;
        }

        return [
            'paidIn' => $paidIn,
            'paidOut' => $paidOut,
            'paidInFmt' => Shift::fmtMin($paidIn),
            'paidOutFmt' => Shift::fmtMin($paidOut),
            'paid' => Shift::paidHours($paidIn, $paidOut, (bool) $p->no_lunch),
            'shiftIn' => $shiftIn,
            'shiftOut' => $shiftOut,
            'source' => $source,
            'adjusted' => $adjusted,
        ];
    }

    /** Paid hours only — convenience for the payroll summations. */
    public static function paidHours(Punch $p): float
    {
        return self::settle($p)['paid'] ?? 0.0;
    }
}
