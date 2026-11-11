<?php

namespace App\Services\Payment;

use App\Models\Order;

interface PaymentGateway
{
    /**
     * Charge the customer for an order.
     *
     * @return array{status: 'completed'|'failed', transaction_id: ?string, message?: string}
     *
     * @throws PaymentUnavailable when the gateway cannot be used right now
     */
    public function charge(Order $order, float $amount, ?string $method = null): array;
}
