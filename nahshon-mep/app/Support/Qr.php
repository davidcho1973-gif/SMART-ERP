<?php

namespace App\Support;

class Qr
{
    /** Deterministic decorative QR-style SVG (prototype visual, not a real payload). */
    public static function pattern(int $seedArg = 11): string
    {
        $n = 25;
        $cell = 100 / $n;
        $seed = $seedArg ?: 11;
        $rnd = function () use (&$seed) {
            $seed = ($seed * 1103515245 + 12345) & 0x7fffffff;
            return $seed / 0x7fffffff;
        };

        $rects = '';
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                if (($x < 7 && $y < 7) || ($x >= $n - 7 && $y < 7) || ($x < 7 && $y >= $n - 7)) {
                    continue;
                }
                if ($rnd() > 0.5) {
                    $rects .= '<rect x="' . number_format($x * $cell, 2, '.', '') . '" y="' . number_format($y * $cell, 2, '.', '')
                        . '" width="' . number_format($cell, 2, '.', '') . '" height="' . number_format($cell, 2, '.', '') . '"/>';
                }
            }
        }

        $finder = function ($fx, $fy) use ($cell) {
            return '<rect x="' . number_format($fx, 2, '.', '') . '" y="' . number_format($fy, 2, '.', '')
                . '" width="' . number_format($cell * 7, 2, '.', '') . '" height="' . number_format($cell * 7, 2, '.', '')
                . '" rx="4" fill="none" stroke="#16181D" stroke-width="' . number_format($cell, 2, '.', '') . '"/>'
                . '<rect x="' . number_format($fx + $cell * 2, 2, '.', '') . '" y="' . number_format($fy + $cell * 2, 2, '.', '')
                . '" width="' . number_format($cell * 3, 2, '.', '') . '" height="' . number_format($cell * 3, 2, '.', '') . '" rx="2"/>';
        };

        $far = $cell * ($n - 7);

        return '<svg width="100%" height="100%" viewBox="0 0 100 100" fill="#16181D">'
            . $rects . $finder(0, 0) . $finder($far, 0) . $finder(0, $far) . '</svg>';
    }

    /** Seed derived from team identity (mirrors teamQrSvg). */
    public static function seedFor(string ...$parts): int
    {
        $seed = 0;
        $s = implode('', $parts);
        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $seed = ($seed * 31 + ord($s[$i])) & 0x7fffffff;
        }
        return $seed ?: 11;
    }
}
