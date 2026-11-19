<?php

namespace App\Support;

final class Geo
{
    /** Great-circle distance in kilometres. */
    public static function km(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /** Distances shown to people are rounded up to the next half kilometre (5 km and above: whole km), so many requests cannot pin an address down. */
    public static function fuzzy(float $km): float
    {
        return $km >= 5 ? (float) ceil($km) : max(0.5, ceil($km * 2) / 2);
    }
}
