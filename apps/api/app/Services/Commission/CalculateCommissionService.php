<?php

namespace App\Services\Commission;

class CalculateCommissionService
{
    /** @return array{percentage: float, amount: float} */
    public function calculate(float|int|string $amount): array
    {
        $percentage = (float) config('platform.commission_percentage');

        return [
            'percentage' => $percentage,
            'amount' => round(((float) $amount * $percentage) / 100, 2),
        ];
    }
}
