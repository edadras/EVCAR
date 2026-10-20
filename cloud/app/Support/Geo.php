<?php

namespace App\Support;

class Geo
{
    public static function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /** زاویه حرکت از نقطه اول به دوم (۰ = شمال، ۹۰ = شرق). */
    public static function bearingDeg(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $lat1r = deg2rad($lat1);
        $lat2r = deg2rad($lat2);
        $dLng = deg2rad($lng2 - $lng1);
        $y = sin($dLng) * cos($lat2r);
        $x = cos($lat1r) * sin($lat2r) - sin($lat1r) * cos($lat2r) * cos($dLng);
        $bearing = rad2deg(atan2($y, $x));

        return fmod($bearing + 360, 360);
    }

    public static function formatDistance(?float $km): string
    {
        if ($km === null) {
            return '—';
        }
        if ($km < 1) {
            return Persian::digits((int) round($km * 1000)).' متر';
        }

        return Persian::digits(round($km, 1)).' کیلومتر';
    }

    /** Tehran center — fallback when user location unknown */
    public static function defaultRef(): array
    {
        return ['lat' => 35.6892, 'lng' => 51.3890];
    }
}
