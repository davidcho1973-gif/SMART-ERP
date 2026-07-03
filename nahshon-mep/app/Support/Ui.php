<?php

namespace App\Support;

/** Inline-style helpers mirroring the prototype's style functions (keeps Blade readable). */
class Ui
{
    public static function tab(bool $a): string
    {
        return $a
            ? 'padding:5px 12px;border-radius:8px;border:none;background:#E85D2A;color:#fff;font-size:12px;font-weight:600;cursor:pointer;'
            : 'padding:5px 12px;border-radius:8px;border:none;background:rgba(255,255,255,0.1);color:rgba(255,255,255,0.7);font-size:12px;font-weight:600;cursor:pointer;';
    }

    public static function langBtn(bool $a): string
    {
        return $a
            ? 'padding:5px 9px;border-radius:7px;border:none;background:#fff;color:#16181D;font-size:11px;font-weight:700;cursor:pointer;'
            : 'padding:5px 9px;border-radius:7px;border:none;background:transparent;color:rgba(255,255,255,0.6);font-size:11px;font-weight:700;cursor:pointer;';
    }

    public static function navItem(bool $a): string
    {
        return $a
            ? 'display:flex;align-items:center;gap:11px;width:100%;padding:10px 12px;border:none;border-radius:10px;background:#E85D2A;color:#fff;font-size:13.5px;font-weight:600;cursor:pointer;text-align:left;'
            : 'display:flex;align-items:center;gap:11px;width:100%;padding:10px 12px;border:none;border-radius:10px;background:transparent;color:rgba(255,255,255,0.62);font-size:13.5px;font-weight:500;cursor:pointer;text-align:left;';
    }

    public static function seg(bool $a): string
    {
        return $a
            ? 'padding:6px 14px;border:none;border-radius:8px;background:#16181D;color:#fff;font-size:12.5px;font-weight:600;cursor:pointer;font-family:Space Grotesk;'
            : 'padding:6px 14px;border:none;border-radius:8px;background:transparent;color:#8A8880;font-size:12.5px;font-weight:600;cursor:pointer;font-family:Space Grotesk;';
    }

    public static function pill(bool $a): string
    {
        return $a
            ? 'padding:7px 15px;border:none;border-radius:9px;background:#16181D;color:#fff;font-size:12.5px;font-weight:600;cursor:pointer;'
            : 'padding:7px 15px;border:none;border-radius:9px;background:transparent;color:#8A8880;font-size:12.5px;font-weight:600;cursor:pointer;';
    }

    public static function tile(bool $a): string
    {
        return $a
            ? 'flex:1;text-align:left;padding:16px;border:1.5px solid #E85D2A;border-radius:14px;background:#FDF0EA;cursor:pointer;'
            : 'flex:1;text-align:left;padding:16px;border:1.5px solid #E4E2DB;border-radius:14px;background:#fff;cursor:pointer;';
    }

    public static function mLang(bool $a): string
    {
        return $a
            ? 'padding:4px 8px;border:none;border-radius:6px;background:#fff;color:#16181D;font-size:11px;font-weight:700;cursor:pointer;'
            : 'padding:4px 8px;border:none;border-radius:6px;background:transparent;color:#8A8880;font-size:11px;font-weight:700;cursor:pointer;';
    }

    public static function allChip(bool $a): string
    {
        return $a
            ? 'white-space:nowrap;padding:6px 13px;border-radius:20px;border:1.5px solid #16181D;background:#16181D;color:#fff;font-size:12.5px;font-weight:600;cursor:pointer;'
            : 'white-space:nowrap;padding:6px 13px;border-radius:20px;border:1.5px solid #E4E2DB;background:#fff;color:#5A5D64;font-size:12.5px;font-weight:500;cursor:pointer;';
    }

    public static function teamChip(bool $a, string $color): string
    {
        return $a
            ? 'white-space:nowrap;padding:6px 13px;border-radius:20px;border:1.5px solid ' . $color . ';background:' . $color . ';color:#fff;font-size:12.5px;font-weight:600;cursor:pointer;'
            : 'white-space:nowrap;padding:6px 13px;border-radius:20px;border:1.5px solid #E4E2DB;background:#fff;color:#5A5D64;font-size:12.5px;font-weight:500;cursor:pointer;';
    }

    public static function accessSeg(bool $a, string $color): string
    {
        return $a
            ? 'flex:1;padding:8px;border:none;border-radius:8px;background:' . $color . ';color:#fff;font-size:12px;font-weight:600;cursor:pointer;'
            : 'flex:1;padding:8px;border:1px solid #E4E2DB;border-radius:8px;background:#fff;color:#5A5D64;font-size:12px;font-weight:600;cursor:pointer;';
    }

    /** larger access segment used in the badge assign step */
    public static function accessSeg2(bool $a, string $color): string
    {
        return $a
            ? 'flex:1;padding:10px;border:none;border-radius:9px;background:' . $color . ';color:#fff;font-size:13px;font-weight:600;cursor:pointer;'
            : 'flex:1;padding:10px;border:1px solid #E4E2DB;border-radius:9px;background:#fff;color:#5A5D64;font-size:13px;font-weight:600;cursor:pointer;';
    }

    public static function stepStyle(bool $active, bool $done): string
    {
        $color = $active ? '#E85D2A' : ($done ? '#1F9D6B' : '#B7B4AB');
        return 'display:flex;align-items:center;gap:7px;font-size:12.5px;font-weight:600;color:' . $color . ';';
    }

    public static function qrRow(bool $a): string
    {
        return $a
            ? 'display:flex;align-items:center;gap:11px;padding:11px 12px;border-radius:11px;margin-top:6px;cursor:pointer;border:1.5px solid #E85D2A;background:#FDF0EA;'
            : 'display:flex;align-items:center;gap:11px;padding:11px 12px;border-radius:11px;margin-top:6px;cursor:pointer;border:1.5px solid #E4E2DB;background:#fff;';
    }

    public static function ocrField(bool $done): string
    {
        return 'width:100%;margin-top:5px;padding:9px 11px;border:1.5px solid ' . ($done ? '#1F9D6B' : '#E4E2DB')
            . ';border-radius:9px;font-size:13.5px;font-weight:600;background:' . ($done ? '#F1FAF5' : '#FAFAF8') . ';color:#16181D;outline:none;';
    }

    public static function lunchToggle(bool $isNo): string
    {
        return 'font-size:10px;font-weight:700;padding:3px 9px;border-radius:7px;cursor:pointer;border:1px solid '
            . ($isNo ? '#CBD9C2' : '#EBD7C2') . ';background:' . ($isNo ? '#F0F4EE' : '#FBF1E9')
            . ';color:' . ($isNo ? '#5A7A4A' : '#C97A34') . ';';
    }
}
