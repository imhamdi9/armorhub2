<?php

namespace App\Services\Payment;

use App\Models\Order;
use Illuminate\Support\Facades\Cache;

/**
 * Charges through whichever provider the admin has activated. Every payment in the platform starts here, so this is where asking twice is made
 * harmless: while one attempt is being started a second is refused, and asking again for the same amount shortly after gets the same answer
 * (the same payment page) instead of a new one that could be paid as well.
 */
class PaymentManager implements PaymentGateway
{
    /** How long a started payment is offered again before a new one is opened. */
    public const REUSE_MINUTES = 15;

    public static function cacheKey(int $paymentId): string
    {
        return "payment-attempt:{$paymentId}";
    }

    /** The attempt is over without money (cancelled or expired at the provider): the next one starts afresh. */
    public static function forget(int $paymentId): void
    {
        Cache::forget(self::cacheKey($paymentId));
    }

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

        $key = self::cacheKey((int) $order->id);
        $lock = Cache::lock($key.':lock', 60);
        abort_unless($lock->get(), 409, __('This payment is already being processed.'));

        try {
            $same = [$provider->key(), sha1((string) json_encode($config)), number_format($amount, 2, '.', ''), strtoupper((string) $order->currency), (string) $method];
            $before = Cache::get($key);
            if ($before && $before['same'] === $same) {
                return $before['result'];
            }

            $result = $provider->charge($order, $amount, $method, $config);
            $result['provider'] = $provider->key();
            if ($result['status'] !== 'failed') {
                Cache::put($key, ['same' => $same, 'result' => $result], now()->addMinutes(self::REUSE_MINUTES));
            }

            return $result;
        } finally {
            $lock->release();
        }
    }
}
