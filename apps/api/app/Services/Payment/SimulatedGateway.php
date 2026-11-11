<?php

namespace App\Services\Payment;

use App\Models\Order;
use Illuminate\Support\Str;

/**
 * Charges nobody. For development, demos and tests only.
 * Replace with a real gateway implementation (Stripe, Moyasar...) before
 * taking real money, then set PLATFORM_PAYMENT_GATEWAY accordingly.
 */
class SimulatedGateway implements PaymentGateway
{
    public function charge(Order $order, float $amount, ?string $method = null): array
    {
        if (app()->isProduction() && ! config('platform.allow_simulated_payments_in_production')) {
            throw new PaymentUnavailable('Online payments are not configured yet.');
        }

        return ['status' => 'completed', 'transaction_id' => 'SIM-'.Str::upper(Str::random(12))];
    }
}
