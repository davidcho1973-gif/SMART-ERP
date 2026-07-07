<?php

namespace App\Support;

/**
 * Shift-time settlement rules (Arizona site):
 *  - Grace window ±30 min: a punch within 30 min of the scheduled time snaps to schedule.
 *  - 1h unpaid lunch (11:00–12:00), counted only when present through it and not skipped.
 *  - Paid hours = (adjusted out − adjusted in) − lunch.
 */
class Shift
{
    /**
     * A worker cannot clock out within this many minutes of clocking in — soaks up
     * double-taps / duplicate GPS callbacks and accidental immediate clock-outs.
     */
    public const MIN_OUT_GAP_MIN = 5;

    /** Parse "6:52 AM" → minutes since midnight. */
    public static function minOf(string $str): int
    {
        if (! preg_match('/(\d+):(\d+)\s*(AM|PM)/i', $str, $m)) {
            return 0;
        }
        $h = ((int) $m[1]) % 12;
        if (strcasecmp($m[3], 'pm') === 0) {
            $h += 12;
        }
        return $h * 60 + (int) $m[2];
    }

    /** Minutes since midnight → "6:00 AM". */
    public static function fmtMin(int $mn): string
    {
        $h = intdiv($mn, 60);
        $m = $mn % 60;
        $ap = $h < 12 ? 'AM' : 'PM';
        $hh = $h % 12;
        if ($hh === 0) {
            $hh = 12;
        }
        return $hh . ':' . str_pad((string) $m, 2, '0', STR_PAD_LEFT) . ' ' . $ap;
    }

    /** ±30 min grace window (minutes) around the scheduled shift boundary. */
    public const GRACE_MIN = 30;

    /** Snap an actual punch to the scheduled time when within the ±30 min grace window. */
    public static function snap(int $actual, int $sched): int
    {
        return abs($actual - $sched) <= self::GRACE_MIN ? $sched : $actual;
    }

    /**
     * Paid hours for a settled in/out (minutes), deducting the 1h lunch when the
     * span covers 11:00–12:00 and lunch wasn't skipped. Never negative.
     */
    public static function paidHours(int $paidIn, int $paidOut, bool $noLunch): float
    {
        $span = $paidOut - $paidIn;
        $tookLunch = ! $noLunch && $paidIn <= 660 && $paidOut >= 720;

        return max(0.0, ($span - ($tookLunch ? 60 : 0)) / 60);
    }

    /**
     * @return array{inFmt:string,outFmt:string,rawIn:string,rawOut:string,adjusted:bool,lunch:float,noLunch:bool,paid:float}
     */
    public static function compute(string $inStr, string $outStr, int $schedIn, int $schedOut, bool $noLunch): array
    {
        $ri = self::minOf($inStr);
        $ro = self::minOf($outStr);
        $ai = self::snap($ri, $schedIn);
        $ao = self::snap($ro, $schedOut);
        $adjusted = $ai !== $ri || $ao !== $ro;
        $span = $ao - $ai;
        // full lunch (11:00-12:00 = 660-720) counted only if present through it and not skipped
        $tookLunch = ! $noLunch && $ai <= 660 && $ao >= 720;
        $lunch = $tookLunch ? 60 : 0;
        $paid = ($span - $lunch) / 60;

        return [
            'inFmt' => self::fmtMin($ai),
            'outFmt' => self::fmtMin($ao),
            'rawIn' => $inStr,
            'rawOut' => $outStr,
            'adjusted' => $adjusted,
            'lunch' => $lunch / 60,
            'noLunch' => $noLunch,
            'paid' => $paid,
        ];
    }
}
