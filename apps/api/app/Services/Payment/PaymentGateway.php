<?php

namespace App\Services\Payment;

use App\Models\Order;

interface PaymentGateway
{
    /**
     * Start charging the customer for an order.
     *
     * - "completed": money was taken immediately (e.g. the simulated gateway)
     * - "redirect":  send the customer to `url` to pay; a webhook confirms it later
     * - "failed":    declined or could not start; `message` says why
     *
     * @return array{status: 'completed'|'redirect'|'failed', transaction_id: ?string, provider?: string, url?: string, message?: string}
     *
     * @throws PaymentUnavailable when the gateway cannot be used right now
     */
    public function charge(Order $order, float $amount, ?string $method = null): array;
}
