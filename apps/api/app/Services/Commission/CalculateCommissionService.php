<?php

namespace App\Services\Commission;


class CalculateCommissionService
{

    public function calculate($amount)
    {
        $percentage = 10;

        return [
            'percentage' => $percentage,
            'amount' => ($amount * $percentage) / 100,
        ];
    }

}