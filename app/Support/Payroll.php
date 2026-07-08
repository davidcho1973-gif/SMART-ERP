<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\Punch;
use Illuminate\Support\Carbon;

/**
 * Bi-weekly USD payroll. Overtime follows the FLSA workweek rule: each Mon–Sun
 * week of the period caps at 40 regular hours, anything beyond pays 1.5× — the
 * SAME rule the exported Excel register uses, so the screen, the recorded check
 * and the register always agree. No tax withholding (net = gross).
 */
class Payroll
{
    public const PERIOD_REG_CAP = 80;

    public const WEEK_REG_CAP = 40;

    /** Bi-weekly periods anchored on Mon Jun 15 2026 (matches the seeded period). */
    public const PERIOD_ANCHOR = '2026-06-15';

    /** @return array{0:string,1:string,2:string} [startYmd, endYmd, label] for the period containing today */
    public static function currentPeriod(): array
    {
        $anchor = Carbon::parse(self::PERIOD_ANCHOR)->startOfDay();
        $today = now()->startOfDay();
        $days = (int) $anchor->diffInDays($today, false);
        $offset = (int) floor(max(0, $days) / 14) * 14;
        $start = $anchor->copy()->addDays($offset);
        $end = $start->copy()->addDays(13);
        $label = $start->format('M j') . ' – ' . $end->format('M j, Y');

        return [$start->format('Y-m-d'), $end->format('Y-m-d'), $label];
    }

    /**
     * Total paid hours from completed punch records in the period, or null when
     * the employee has no punches yet (caller falls back to the seeded figure).
     */
    public static function periodHoursFromPunches(int $employeeId, string $startYmd, string $endYmd): ?float
    {
        $punches = Punch::where('employee_id', $employeeId)
            ->whereBetween('work_date', [$startYmd, $endYmd])
            ->whereNotNull('in_min')->whereNotNull('out_min')
            ->get();
        if ($punches->isEmpty()) {
            return null;
        }

        return $punches->sum(fn ($p) => max(0, Attendance::paidHours($p)));
    }

    /**
     * Weekly-40h (FLSA) reg/OT breakdown from punches for a whole roster in ONE
     * query. Weeks are the period's own 7-day slices (period starts Monday, so
     * a 14-day period is exactly two Mon–Sun workweeks).
     *
     * @param  array<int,int>  $empIds
     * @return array<int,array{reg:float,ot:float,total:float}> keyed by employee id — only employees WITH punches appear
     */
    public static function periodBreakdowns(array $empIds, string $startYmd, string $endYmd): array
    {
        if ($empIds === []) {
            return [];
        }
        $start = Carbon::parse($startYmd)->startOfDay();
        $punches = Punch::whereIn('employee_id', $empIds)
            ->whereBetween('work_date', [$startYmd, $endYmd])
            ->whereNotNull('in_min')->whereNotNull('out_min')
            ->get();

        $weeks = [];   // [empId][weekIdx] = paid hours
        foreach ($punches as $p) {
            $idx = intdiv(max(0, (int) $start->diffInDays(Carbon::parse($p->work_date)->startOfDay())), 7);
            $weeks[$p->employee_id][$idx] = ($weeks[$p->employee_id][$idx] ?? 0.0) + max(0.0, Attendance::paidHours($p));
        }

        $out = [];
        foreach ($weeks as $empId => $byWeek) {
            $reg = $ot = 0.0;
            foreach ($byWeek as $wk) {
                $reg += min($wk, self::WEEK_REG_CAP);
                $ot += max(0.0, $wk - self::WEEK_REG_CAP);
            }
            $out[$empId] = ['reg' => round($reg, 2), 'ot' => round($ot, 2), 'total' => round($reg + $ot, 2)];
        }

        return $out;
    }

    /**
     * One employee's period breakdown. No punches → zeros in real mode (nobody
     * is paid for unworked time); in demo mode the seeded `wh` figure fills in
     * so the sample roster still shows a plausible payroll.
     *
     * @return array{reg:float,ot:float,total:float}
     */
    public static function breakdownFor(Employee $e, string $startYmd, string $endYmd): array
    {
        $b = self::periodBreakdowns([$e->id], $startYmd, $endYmd);
        if (isset($b[$e->id])) {
            return $b[$e->id];
        }

        return self::fallbackBreakdown($e);
    }

    /** The no-punches fallback: seeded wh in demo, zero in real mode. */
    public static function fallbackBreakdown(Employee $e): array
    {
        if (config('workforce.demo')) {
            $wh = (float) $e->wh;

            return ['reg' => min($wh, self::PERIOD_REG_CAP), 'ot' => max(0.0, $wh - self::PERIOD_REG_CAP), 'total' => $wh];
        }

        return ['reg' => 0.0, 'ot' => 0.0, 'total' => 0.0];
    }

    /** Gross pay from a reg/OT breakdown: reg at 1×, overtime at 1.5×. */
    public static function grossPay(array $b, float $rate): float
    {
        return $b['reg'] * $rate + $b['ot'] * $rate * 1.5;
    }

    /** Guess the scheduled shift from the punch-in time: 6–3 / 7–4 weekdays, 7–2 Saturdays. */
    public static function scheduleFor(int $inMin, bool $saturday = false): array
    {
        if ($saturday) {
            return [420, 840]; // 7:00 AM – 2:00 PM
        }

        return abs($inMin - 360) <= abs($inMin - 420)
            ? [360, 900]   // 6:00 AM – 3:00 PM
            : [420, 960];  // 7:00 AM – 4:00 PM
    }

    /**
     * Legacy period-80h helpers — kept ONLY for the demo `wh` fallback figures
     * and the seeded pay-history mock. Real pay math goes through
     * periodBreakdowns()/grossPay() (weekly 40h).
     */
    public static function gross(int $wh, float $rate): float
    {
        $reg = min($wh, self::PERIOD_REG_CAP);
        $ot = max(0, $wh - self::PERIOD_REG_CAP);
        return $reg * $rate + $ot * $rate * 1.5;
    }

    public static function regHours(int $wh): int
    {
        return min($wh, self::PERIOD_REG_CAP);
    }

    public static function otHours(int $wh): int
    {
        return max(0, $wh - self::PERIOD_REG_CAP);
    }

    /**
     * Reconstruct a plausible day-by-day punch history that sums to the period hours.
     * Mirrors the prototype's payHistoryFor().
     *
     * @param  callable  $tl  fn(string $en, string $es, string $ko): string
     * @return array{days:array<int,array>,reg:int,ot:int,gross:float}
     */
    public static function history(int $wh, int $id, float $rate, callable $tl): array
    {
        // biweekly period: 10 weekdays Jun 15–26 2026
        $wd = [
            ['Jun 15', 'Mon'], ['Jun 16', 'Tue'], ['Jun 17', 'Wed'], ['Jun 18', 'Thu'], ['Jun 19', 'Fri'],
            ['Jun 22', 'Mon'], ['Jun 23', 'Tue'], ['Jun 24', 'Wed'], ['Jun 25', 'Thu'], ['Jun 26', 'Fri'],
        ];
        $base = array_fill(0, count($wd), 8);
        $rem = $wh - 80;
        if ($rem > 0) {
            $i = count($base) - 1;
            while ($rem > 0) {
                $add = min(2, $rem);
                $base[$i] += $add;
                $rem -= $add;
                $i--;
                if ($i < 0) {
                    $i = count($base) - 1;
                }
            }
        } elseif ($rem < 0) {
            $cut = -$rem;
            $i = 0;
            while ($cut > 0 && $i < count($base)) {
                $c = min($cut, $base[$i] - 5, 3);
                $base[$i] -= $c;
                $cut -= $c;
                $i++;
            }
        }

        $startEven = $id % 2 === 0;
        $days = [];
        foreach ($wd as $idx => $d) {
            $paid = $base[$idx];
            $inMin = $startEven ? 360 : 420;       // 6:00 or 7:00
            $outMin = $inMin + ($paid + 1) * 60;   // +1h lunch
            $chips = [];
            if ($paid > 8) {
                $chips[] = ['label' => 'OT +' . ($paid - 8), 'bg' => '#EAF6EF', 'color' => '#1F9D6B'];
            } elseif ($paid < 8) {
                $chips[] = ['label' => $tl('Short day', 'Día corto', '단축 근무'), 'bg' => '#FBF1E9', 'color' => '#C97A34'];
            }
            $days[] = [
                'd' => $d[0], 'dow' => $d[1],
                'inFmt' => Shift::fmtMin($inMin), 'outFmt' => Shift::fmtMin($outMin),
                'paid' => number_format($paid, 1) . 'h', 'chips' => $chips, 'isOt' => $paid > 8,
            ];
        }

        $sum = array_sum($base);
        $reg = min(80, $sum);
        $ot = max(0, $sum - 80);
        $gross = $reg * $rate + $ot * $rate * 1.5;

        return ['days' => $days, 'reg' => $reg, 'ot' => $ot, 'gross' => $gross];
    }
}
