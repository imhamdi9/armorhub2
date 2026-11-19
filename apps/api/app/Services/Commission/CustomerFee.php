<?php

namespace App\Services\Commission;

use App\Support\Settings\Settings;

/**
 * The platform fee a customer pays on top of the invoice (the goods and the accepted quote): a percentage of the invoice value,
 * shown as its own line on the payment page. The commission taken from partners is separate and is still worked out on the invoice alone.
 */
class CustomerFee
{
    public function __construct(private Settings $settings)
    {
    }

    public function rate(): float
    {
        return max(0.0, min(30.0, (float) $this->settings->get('platform.customer_fee_percentage')));
    }

    /** @return array{service_fee_rate: float, service_fee_amount: float} the two order columns for an invoice of this value. */
    public function forInvoice(float|int|string $invoice): array
    {
        $rate = $this->rate();

        return ['service_fee_rate' => $rate, 'service_fee_amount' => round(((float) $invoice * $rate) / 100, 2)];
    }
}
