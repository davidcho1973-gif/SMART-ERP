<?php

namespace App\Support;

/**
 * Bi-weekly USD payroll: 40h/week regular (80h/period), overtime beyond 80h at 1.5×,
 * no tax withholding (net = gross).
 */
class Payroll
{
    public const PERIOD_REG_CAP = 80;

    /** Bi-weekly periods anchored on Mon Jun 15 2026 (matches the seeded period). */
    public const PERIOD_ANCHOR = '2026-06-15';

    /** @return array{0:string,1:string,2:string} [startYmd, endYmd, label] for the period containing today */
    public static function currentPeriod(): array
    {
        $anchor = \Illuminate\Support\Carbon::parse(self::PERIOD_ANCHOR)->startOfDay();
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
        $punches = \App\Models\Punch::where('employee_id', $employeeId)
            ->whereBetween('work_date', [$startYmd, $endYmd])
            ->whereNotNull('in_min')->whereNotNull('out_min')
            ->get();
        if ($punches->isEmpty()) {
            return null;
        }

        return $punches->sum(function ($p) {
            [$si, $so] = self::scheduleFor($p->in_min, \Illuminate\Support\Carbon::parse($p->work_date)->isSaturday());
            return max(0, Shift::compute(Shift::fmtMin($p->in_min), Shift::fmtMin($p->out_min), $si, $so, $p->no_lunch)['paid']);
        });
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

    /** Gross pay for a period given hours worked and hourly rate. */
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
