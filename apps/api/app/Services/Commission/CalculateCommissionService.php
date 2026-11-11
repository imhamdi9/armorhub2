<?php

namespace App\Services\Commission;

use App\Support\Settings\Settings;

class CalculateCommissionService
{
    public function __construct(private Settings $settings)
    {
    }

    /** @return array{percentage: float, amount: float} */
    public function calculate(float|int|string $amount): array
    {
        $percentage = (float) $this->settings->get('platform.commission_percentage');

        return [
            'percentage' => $percentage,
            'amount' => round(((float) $amount * $percentage) / 100, 2),
        ];
    }
}
