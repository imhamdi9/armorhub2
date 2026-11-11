<?php

namespace App\Services\Payment;

class Money
{
    /** Payment providers deal in integers of the currency's smallest unit. */
    public static function minorUnits(float|string $amount, string $currency): int
    {
        $currency = strtoupper($currency);
        $zeroDecimal = ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'];
        $threeDecimal = ['BHD', 'JOD', 'KWD', 'OMR', 'TND'];

        $exponent = in_array($currency, $zeroDecimal, true) ? 0 : (in_array($currency, $threeDecimal, true) ? 3 : 2);

        return (int) round((float) $amount * (10 ** $exponent));
    }
}
