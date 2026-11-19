<?php

namespace App\Services\Payment;

/** A payment provider's webhook, reduced to what the platform needs to know. */
final class PaymentEvent
{
    /**
     * @param  'paid'|'expired'  $type
     * @param  string  $reference  the provider's id for the checkout (matches Payment.transaction_id)
     * @param  int|null  $amountMinor  what was actually paid, in the currency's smallest unit
     * @param  float|null  $amountMajor  or in whole units (12.50) when the provider reports it that way
     */
    public function __construct(
        public readonly string $type,
        public readonly string $reference,
        public readonly ?int $orderId = null,
        public readonly ?int $amountMinor = null,
        public readonly ?string $currency = null,
        public readonly ?float $amountMajor = null,
    ) {
    }
}
