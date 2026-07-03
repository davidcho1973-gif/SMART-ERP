<?php

namespace App\Support;

class Money
{
    /** $1,234 — whole-dollar, thousands-separated (mirrors fmtUSD). */
    public static function usd(float $n): string
    {
        return '$' . number_format(round($n));
    }

    /** $32.50 — two decimals (mirrors fmtRate). */
    public static function rate(float $n): string
    {
        return '$' . number_format($n, 2);
    }
}
