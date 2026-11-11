<?php

namespace App\Services\Location;

use App\Models\Partner;

class FindNearbyPartnersService
{
    public function execute(
        float $latitude,
        float $longitude,
        float $radius = 30
    ) {
        $centers = Partner::query()
            ->where('is_active', true)
            ->where('location_enabled', true)
            ->get();

        return $this->calculateDistances(
            $centers,
            $latitude,
            $longitude,
            $radius
        );
    }


    public function executeWithFallback(
        float $latitude,
        float $longitude,
        ?string $city = null,
        ?string $country = null
    ) {

        // 1) نفس المدينة
        if ($city) {

            $sameCity = Partner::query()
                ->where('is_active', true)
                ->where('location_enabled', true)
                ->where('city', $city)
                ->get();


            if ($sameCity->isNotEmpty()) {

                return $this->calculateDistances(
                    $sameCity,
                    $latitude,
                    $longitude
                );
            }
        }


        // 2) نفس الدولة
        if ($country) {

            $sameCountry = Partner::query()
                ->where('is_active', true)
                ->where('location_enabled', true)
                ->where('country', $country)
                ->get();


            if ($sameCountry->isNotEmpty()) {

                return $this->calculateDistances(
                    $sameCountry,
                    $latitude,
                    $longitude
                );
            }
        }


        // 3) البحث الموسع
        return $this->execute(
            $latitude,
            $longitude,
            300
        );
    }


    private function calculateDistances(
        $centers,
        float $latitude,
        float $longitude,
        ?float $radius = null
    ) {

        return $centers
            ->map(function ($center) use ($latitude, $longitude) {

                $earthRadius = 6371;

                $latDistance = deg2rad(
                    $center->latitude - $latitude
                );

                $lonDistance = deg2rad(
                    $center->longitude - $longitude
                );


                $a =
                    sin($latDistance / 2) *
                    sin($latDistance / 2)
                    +
                    cos(deg2rad($latitude))
                    *
                    cos(deg2rad($center->latitude))
                    *
                    sin($lonDistance / 2)
                    *
                    sin($lonDistance / 2);


                $distance = $earthRadius *
                    2 *
                    atan2(
                        sqrt($a),
                        sqrt(1 - $a)
                    );


                $center->distance_km = round(
                    $distance,
                    2
                );


                return $center;

            })

            ->filter(function ($center) use ($radius) {

                if (!$radius) {
                    return true;
                }

                return $center->distance_km <= $radius;

            })

            ->sortBy('distance_km')
            ->values();
    }
}