<?php

namespace App\Support;

use App\Models\Absence;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\Punch;
use App\Models\Team;
use Illuminate\Support\Carbon;

/**
 * Resolves one worker's attendance status for a given day, in one place shared by
 * the dashboard, the crew panel and the worker app. One status → one colour, used
 * everywhere so the board reads at a glance.
 *
 * Precedence (first match wins):
 *   terminated → leave(approved, covers today) → absence(excused/unexcused) →
 *   punch(working/early/done) → no-punch(off / 미출근-before-cutoff /
 *   미출근-after-cutoff on a scheduled day / 무단결근 on a past scheduled day)
 */
class WorkerStatus
{
    /** minutes after the shift start by which a no-show is flagged 미출근 */
    public const CUTOFF_GRACE_MIN = 30;

    /** unexcused no-shows within the window that trigger an escalation */
    public const UNEXCUSED_ALERT = 3;

    public const UNEXCUSED_WINDOW_DAYS = 30;

    /** default shift start when a crew has none configured (6:00 AM guess). */
    private const DEFAULT_START_MIN = 360;

    /**
     * @param  ?callable  $tl  fn(string $en, string $es, string $ko): string — localizes the label+detail
     * @return array{key:string,label:string,color:string,bg:string,dot:string,detail:string,order:int}
     */
    public static function resolve(
        Employee $e,
        ?Team $team,
        ?Punch $punch,
        ?Leave $leave,
        ?Absence $absence,
        string $ymd,
        Carbon $now,
        ?callable $tl = null
    ): array {
        $t = $tl ?? fn ($en, $es, $ko) => $ko;   // default Korean (company's primary language)
        $isToday = $ymd === $now->format('Y-m-d');
        $sat = Carbon::parse($ymd)->isSaturday();

        // 1) terminated — the record is closed
        if ($e->emp === 'terminated') {
            return self::make('terminated', $t('Former', 'Baja', '퇴사'), '#8A8880', '#ECEBE6', '#A7A49B',
                $t('Left', 'Baja', '퇴사').($e->term ? ' · '.$e->term : ''), 9);
        }

        // 2) approved leave covering the day
        if ($leave && $leave->covers($ymd)) {
            $range = self::fmtDate($leave->start_date).' – '.self::fmtDate($leave->end_date);
            $why = $leave->reason ? ' · '.$leave->reason : '';

            return self::make('leave', $t('On leave', 'De permiso', '휴가중'), '#3B72E0', '#E9F1FB', '#3B72E0',
                $t('Leave', 'Permiso', '휴가').' '.$range.$why, 5);
        }

        // 3) an explicit absence record for the day
        if ($absence) {
            if ($absence->kind === 'unexcused') {
                return self::make('unexcused', $t('Absent', 'Ausente', '결근'), '#C0392B', '#FBE9E7', '#D9483B',
                    $t('No-call no-show', 'Falta sin aviso', '무단 결근').($absence->reason ? ' · '.$absence->reason : ' · '.$t('no contact', 'sin contacto', '연락 두절')), 8);
            }

            return self::make('absent', $t('Absent', 'Ausente', '결근'), '#C0392B', '#FBE9E7', '#D9483B',
                $t('Absent', 'Ausente', '결근').($absence->reason ? ' · '.$absence->reason : ''), 7);
        }

        // 4) a punch exists → working / early-leave / done
        if ($punch && $punch->in_min !== null) {
            if ($punch->out_min === null) {
                return self::make('working', $t('Working', 'Trabajando', '근무중'), '#1F9D6B', '#E7F4EE', '#1F9D6B',
                    Shift::fmtMin($punch->in_min).' '.$t('in', 'entrada', '출근').($e->role ? ' · '.$e->role : ''), 1);
            }
            if ($punch->early_reason) {
                return self::make('early', $t('Left early', 'Salió antes', '조퇴'), '#C17A1A', '#FBF1DF', '#E8A33D',
                    $t('Left early', 'Salió antes', '조퇴').' · '.Shift::fmtMin($punch->out_min).' · '.$t('reason', 'motivo', '사유').': '.$punch->early_reason, 3);
            }

            // an auto-filled clock-out (worker forgot to clock out; end-of-day close
            // stamped the scheduled end) is flagged so a lead knows to verify it
            $autoTag = $punch->out_auto
                ? ' · '.$t('auto — verify', 'auto — verificar', '자동 마감 · 확인 필요')
                : '';

            return self::make('done', $t('Clocked out', 'Salió', '퇴근'), '#5A5D64', '#EFEFEC', '#5A5D64',
                Shift::fmtMin($punch->out_min).' '.$t('out', 'salida', '퇴근').$autoTag, 2);
        }

        // 5) no punch. On a non-scheduled day the person is simply off.
        if (! self::scheduledDay($team, $ymd)) {
            return self::make('off', $t('Off', 'Libre', '휴무'), '#9AA0A6', '#F4F3EF', '#B7B4AB', '', 6);
        }

        // scheduled day, no punch: past day → 무단결근 (end-of-day close should have
        // recorded it, but derive it too so the board is correct immediately);
        // today → 미출근 (before cutoff neutral, after cutoff a warning)
        if (! $isToday) {
            return self::make('unexcused', $t('Absent', 'Ausente', '결근'), '#C0392B', '#FBE9E7', '#D9483B',
                $t('No-call no-show · unrecorded', 'Falta sin registrar', '무단 결근 · 미기록'), 8);
        }
        $cutoff = self::cutoffMin($team, $sat);
        $nowMin = $now->hour * 60 + $now->minute;
        if ($nowMin >= $cutoff) {
            return self::make('missing', $t('Not in', 'Sin fichar', '미출근'), '#C0522B', '#FBEDE7', '#E8A33D',
                Shift::fmtMin($cutoff).' '.$t('past, not in', 'pasó, sin fichar', '지나도 미출근'), 4);
        }

        return self::make('pending', $t('Not in', 'Sin fichar', '미출근'), '#9AA0A6', '#F4F3EF', '#B7B4AB',
            $t('before shift', 'antes del turno', '출근 전'), 6);
    }

    /** Is the crew scheduled to work this day? Sun never; Sat only with a Sat shift; Mon–Fri always. */
    public static function scheduledDay(?Team $team, string $ymd): bool
    {
        $d = Carbon::parse($ymd);
        if ($d->isSunday()) {
            return false;
        }
        if ($d->isSaturday()) {
            return $team && $team->sat_in !== null && $team->sat_out !== null;
        }

        return true;
    }

    /** Cutoff (minutes since midnight): shift start + grace, or a 6:00 default. */
    public static function cutoffMin(?Team $team, bool $saturday): int
    {
        $start = self::DEFAULT_START_MIN;
        if ($team) {
            if ($saturday && $team->sat_in !== null) {
                $start = (int) $team->sat_in;
            } elseif ($team->shift_in !== null) {
                $start = (int) $team->shift_in;
            }
        }

        return $start + self::CUTOFF_GRACE_MIN;
    }

    /**
     * Count of unexcused no-shows (무단결근) in the trailing window — explicit
     * unexcused absence rows, which the end-of-day close job keeps complete.
     */
    public static function unexcusedCount(int $employeeId, Carbon $now): int
    {
        $from = $now->copy()->subDays(self::UNEXCUSED_WINDOW_DAYS)->format('Y-m-d');

        return Absence::where('employee_id', $employeeId)
            ->where('kind', 'unexcused')
            ->where('work_date', '>=', $from)
            ->count();
    }

    /** "YYYY-MM-DD" → "MM/DD" for compact chips. */
    private static function fmtDate(string $ymd): string
    {
        return preg_match('/^\d{4}-(\d{2})-(\d{2})$/', $ymd, $m) ? $m[1].'/'.$m[2] : $ymd;
    }

    /** @return array{key:string,label:string,color:string,bg:string,dot:string,detail:string,order:int} */
    private static function make(string $key, string $label, string $color, string $bg, string $dot, string $detail, int $order): array
    {
        return compact('key', 'label', 'color', 'bg', 'dot', 'detail', 'order');
    }
}
