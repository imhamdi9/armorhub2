<?php

namespace App\Services\Payment;

use App\Models\Order;

/** Charges through whichever provider the admin has activated. */
class PaymentManager implements PaymentGateway
{
    public function __construct(private PaymentProviders $providers)
    {
    }

    public function charge(Order $order, float $amount, ?string $method = null): array
    {
        $provider = $this->providers->active();
        $config = $this->providers->config($provider->key());

        if (! $provider->isConfigured($config)) {
            throw new PaymentUnavailable('Online payments are not configured yet.');
        }

        $result = $provider->charge($order, $amount, $method, $config);
        $result['provider'] = $provider->key();

        return $result;
    }
}
