<?php

namespace App\Services\Payment\Providers;

use App\Models\Order;
use App\Services\Payment\InvalidWebhook;
use App\Services\Payment\Money;
use App\Services\Payment\PaymentEvent;
use App\Services\Payment\PaymentProvider;
use App\Services\Payment\PaymentUnavailable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/** Stripe Checkout (hosted page). Orders are marked paid only by the signed webhook. */
class StripeProvider implements PaymentProvider
{
    private const TOLERANCE_SECONDS = 300;

    public function key(): string
    {
        return 'stripe';
    }

    public function label(): string
    {
        return 'Stripe Checkout';
    }

    public function help(): string
    {
        return 'In Stripe, add a webhook endpoint with the URL below for the events checkout.session.completed and checkout.session.expired, then paste its signing secret here.';
    }

    public function fields(): array
    {
        return [
            'secret_key' => ['label' => 'Secret key (sk_...)', 'type' => 'secret', 'required' => true,
                'default' => fn () => config('services.stripe.secret')],
            'webhook_secret' => ['label' => 'Webhook signing secret (whsec_...)', 'type' => 'secret', 'required' => true,
                'default' => fn () => config('services.stripe.webhook_secret')],
        ];
    }

    public function isConfigured(array $config): bool
    {
        return filled($config['secret_key'] ?? null) && filled($config['webhook_secret'] ?? null);
    }

    public function charge(Order $order, float $amount, ?string $method, array $config): array
    {
        $web = rtrim(config('platform.web_url'), '/');

        try {
            $response = Http::asForm()->withBasicAuth($config['secret_key'], '')->timeout(20)
                ->post(rtrim(config('services.stripe.api_base'), '/').'/v1/checkout/sessions', [
                    'mode' => 'payment',
                    'client_reference_id' => (string) $order->id,
                    'metadata' => ['order_id' => (string) $order->id],
                    'line_items' => [[
                        'quantity' => 1,
                        'price_data' => [
                            'currency' => strtolower($order->currency),
                            'unit_amount' => Money::minorUnits($amount, $order->currency),
                            'product_data' => ['name' => "Order #{$order->id}"],
                        ],
                    ]],
                    'success_url' => "{$web}/dashboard/orders/{$order->id}?checkout=success",
                    'cancel_url' => "{$web}/dashboard/orders/{$order->id}?checkout=cancelled",
                ]);
        } catch (ConnectionException) {
            throw new PaymentUnavailable('The payment provider could not be reached. Please try again.');
        }

        if ($response->failed() || ! $response->json('url')) {
            report(new \RuntimeException('Stripe checkout failed: '.$response->body()));

            return ['status' => 'failed', 'transaction_id' => null, 'message' => 'The payment could not be started.'];
        }

        return ['status' => 'redirect', 'transaction_id' => $response->json('id'), 'url' => $response->json('url')];
    }

    public function parseWebhook(Request $request, array $config): ?PaymentEvent
    {
        $payload = $request->getContent();
        $header = (string) $request->header('Stripe-Signature');
        $secret = $config['webhook_secret'] ?? null;

        if (! $secret || $header === '') {
            throw new InvalidWebhook('Missing signature or secret.');
        }

        // "t=timestamp,v1=hmac[,v1=hmac]" per Stripe's signing scheme.
        $parts = [];
        foreach (explode(',', $header) as $pair) {
            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $parts[$key][] = $value;
        }

        $timestamp = (int) ($parts['t'][0] ?? 0);

        if (abs(now()->timestamp - $timestamp) > self::TOLERANCE_SECONDS) {
            throw new InvalidWebhook('Stale or missing timestamp.');
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

        if (! collect($parts['v1'] ?? [])->contains(fn ($candidate) => hash_equals($expected, $candidate))) {
            throw new InvalidWebhook('Signature mismatch.');
        }

        $event = json_decode($payload, true) ?: [];
        $object = $event['data']['object'] ?? [];

        return match ($event['type'] ?? null) {
            'checkout.session.completed' => ($object['payment_status'] ?? null) === 'paid'
                ? new PaymentEvent('paid', (string) ($object['id'] ?? ''), isset($object['metadata']['order_id']) ? (int) $object['metadata']['order_id'] : null,
                    isset($object['amount_total']) ? (int) $object['amount_total'] : null, $object['currency'] ?? null)
                : null,
            'checkout.session.expired' => new PaymentEvent('expired', (string) ($object['id'] ?? '')),
            default => null,
        };
    }

    public function test(array $config): array
    {
        if (! $this->isConfigured($config)) {
            return ['ok' => false, 'message' => 'Enter the secret key and webhook secret first.'];
        }

        try {
            $response = Http::withBasicAuth($config['secret_key'], '')->timeout(10)->get(rtrim(config('services.stripe.api_base'), '/').'/v1/balance');
        } catch (\Throwable) {
            return ['ok' => false, 'message' => 'Could not reach Stripe.'];
        }

        return $response->ok()
            ? ['ok' => true, 'message' => 'Stripe accepted the secret key.']
            : ['ok' => false, 'message' => 'Stripe rejected the key: '.($response->json('error.message') ?? 'unknown error')];
    }
}
