<?php

namespace App\Services\Payment;

use App\Models\Order;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Stripe Checkout (hosted payment page). The customer pays on Stripe; the order is
 * marked paid by the signed webhook (see StripeWebhookController), never by the browser.
 */
class StripeGateway implements PaymentGateway
{
    public function charge(Order $order, float $amount, ?string $method = null): array
    {
        $secret = config('services.stripe.secret');

        if (! $secret) {
            throw new PaymentUnavailable('Online payments are not configured yet.');
        }

        $web = rtrim(config('platform.web_url'), '/');

        try {
            $response = Http::asForm()->withBasicAuth($secret, '')->timeout(20)->post(rtrim(config('services.stripe.api_base'), '/').'/v1/checkout/sessions', [
                'mode' => 'payment',
                'client_reference_id' => (string) $order->id,
                'metadata' => ['order_id' => (string) $order->id],
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => strtolower($order->currency),
                        'unit_amount' => self::minorUnits($amount, $order->currency),
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

    /** Stripe amounts are integers in the currency's smallest unit. */
    public static function minorUnits(float|string $amount, string $currency): int
    {
        $currency = strtoupper($currency);
        $zeroDecimal = ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'];
        $threeDecimal = ['BHD', 'JOD', 'KWD', 'OMR', 'TND'];

        $exponent = in_array($currency, $zeroDecimal, true) ? 0 : (in_array($currency, $threeDecimal, true) ? 3 : 2);

        return (int) round((float) $amount * (10 ** $exponent));
    }
}
