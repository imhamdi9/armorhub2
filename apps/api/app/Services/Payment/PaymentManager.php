<?php

namespace App\Services\Payment;

use App\Models\Order;

/** Charges through whichever provider the admin has activated. */
class PaymentManager implements PaymentGateway
{
    public function __construct(private PaymentProviders $providers, private \App\Support\Settings\Settings $settings)
    {
    }

    /** The order is paid with the payment settings of the market it belongs to (its own provider and keys, or the platform-wide ones). */
    public function charge(Order $order, float $amount, ?string $method = null): array
    {
        $tenantId = \App\Models\Company::withoutGlobalScopes()->whereKey($order->company_id)->value('tenant_id');

        return $this->settings->inMarket($tenantId, fn () => $this->chargeNow($order, $amount, $method));
    }

    private function chargeNow(Order $order, float $amount, ?string $method): array
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
