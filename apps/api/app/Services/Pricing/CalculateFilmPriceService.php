<?php

namespace App\Services\Pricing;

use App\Models\FilmPriceRule;


class CalculateFilmPriceService
{
    public function calculate(
        int $filmId,
        array $vehicleParts
    ): array {

        $rules = FilmPriceRule::query()
            ->where('film_id', $filmId)
            ->whereIn(
                'vehicle_part_id',
                $vehicleParts
            )
            ->where('is_active', true)
            ->get();


        $total = $rules->sum('price');


        return [
            'film_id' => $filmId,
            'parts_count' => $rules->count(),
            'total' => $total,
            'currency' => 'USD',
        ];
    }
}