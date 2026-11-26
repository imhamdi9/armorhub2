<?php

namespace App\Services\Commission;

use App\Support\Settings\Settings;

class CalculateCommissionService
{
    public function __construct(private Settings $settings)
    {
    }

    /** @return array{percentage: float, amount: float} */
    public function calculate(float|int|string $amount, ?float $percentage = null): array
    {
        // The commission of the partner's or seller's plan when one is given, else the platform default.
        $percentage ??= (float) $this->settings->get('platform.commission_percentage');

        return [
            'percentage' => $percentage,
            'amount' => round(((float) $amount * $percentage) / 100, 2),
        ];
    }
}
