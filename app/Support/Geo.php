<?php

namespace App\Support;

use App\Models\Site;

/**
 * Geolocation helpers for on-site attendance verification.
 * Distances are computed with the Haversine formula (metres).
 */
class Geo
{
    /** Fallback geofence radius when a site hasn't set its own (metres). */
    public const DEFAULT_RADIUS_M = 150;

    /** Above this reported accuracy the fix is too coarse to trust an "on-site" result (metres). */
    public const MAX_TRUSTED_ACC_M = 500;

    /**
     * Sanitize browser-reported coordinates. Returns null for missing or
     * out-of-range values (garbage / spoofed nonsense), otherwise a clean tuple.
     *
     * @return array{lat:float,lng:float,acc:float|null}|null
     */
    public static function coords(float|string|null $lat, float|string|null $lng, float|string|null $acc): ?array
    {
        if ($lat === null || $lng === null || $lat === '' || $lng === '') {
            return null;
        }
        $la = (float) $lat;
        $ln = (float) $lng;
        if ($la < -90 || $la > 90 || $ln < -180 || $ln > 180) {
            return null;
        }
        $ac = ($acc !== null && $acc !== '') ? (float) $acc : null;

        return ['lat' => $la, 'lng' => $ln, 'acc' => ($ac !== null && $ac >= 0) ? $ac : null];
    }

    /** Great-circle distance between two lat/lng points, in metres. */
    public static function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371000.0; // mean Earth radius (m)
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earth * 2 * atan2(sqrt($a), sqrt(max(0.0, 1 - $a)));
    }

    /**
     * Check a captured coordinate against a site's geofence.
     *
     * @return array{0: float|null, 1: bool|null} [distance in metres, inside-radius flag].
     *                                            Both null when the coordinate or the site's location is unavailable —
     *                                            attendance is never blocked, we just can't verify it.
     */
    public static function verifySite(?Site $site, float|string|null $lat, float|string|null $lng): array
    {
        if ($site === null || $lat === null || $lng === null || $lat === '' || $lng === ''
            || $site->lat === null || $site->lng === null) {
            return [null, null];
        }

        $dist = self::distanceMeters((float) $site->lat, (float) $site->lng, (float) $lat, (float) $lng);
        $radius = (int) ($site->radius_m ?: self::DEFAULT_RADIUS_M);

        return [$dist, $dist <= $radius];
    }
}
