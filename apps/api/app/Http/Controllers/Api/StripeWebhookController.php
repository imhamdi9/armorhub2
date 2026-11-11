<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payment\PaymentFinalizer;
use App\Services\Payment\StripeGateway;
use Illuminate\Http\Request;

/** Stripe calls this when a customer finishes (or abandons) Checkout. */
class StripeWebhookController extends Controller
{
    private const TOLERANCE_SECONDS = 300;

    public function handle(Request $request, PaymentFinalizer $finalizer)
    {
        $payload = $request->getContent();

        if (! $this->validSignature($payload, (string) $request->header('Stripe-Signature'))) {
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        $event = json_decode($payload, true) ?: [];
        $object = $event['data']['object'] ?? [];

        match ($event['type'] ?? null) {
            'checkout.session.completed' => $this->completed($object, $finalizer),
            'checkout.session.expired' => $this->expired($object),
            default => null,
        };

        // Always acknowledge events we understand or ignore, so Stripe stops retrying.
        return response()->json(['received' => true]);
    }

    private function completed(array $session, PaymentFinalizer $finalizer): void
    {
        if (($session['payment_status'] ?? null) !== 'paid') {
            return;
        }

        $order = Order::withoutGlobalScopes()->find($session['metadata']['order_id'] ?? null);

        if (! $order) {
            return;
        }

        // Never trust the event alone: what was paid must match what the order costs.
        $expected = StripeGateway::minorUnits($order->total_amount, $order->currency);

        if (($session['amount_total'] ?? null) !== $expected
            || strtoupper($session['currency'] ?? '') !== strtoupper($order->currency)) {
            report(new \RuntimeException("Stripe amount mismatch for order {$order->id}: ".json_encode($session)));

            return;
        }

        $finalizer->complete($order, ['transaction_id' => $session['id']], 'card');
    }

    private function expired(array $session): void
    {
        Payment::withoutGlobalScopes()
            ->where('transaction_id', $session['id'] ?? '')
            ->where('status', 'pending')
            ->update(['status' => 'expired']);
    }

    /** "t=timestamp,v1=hmac" per Stripe's signing scheme. */
    private function validSignature(string $payload, string $header): bool
    {
        $secret = config('services.stripe.webhook_secret');

        if (! $secret || $header === '') {
            return false;
        }

        $parts = [];
        foreach (explode(',', $header) as $pair) {
            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $parts[$key][] = $value;
        }

        $timestamp = (int) ($parts['t'][0] ?? 0);

        if (abs(time() - $timestamp) > self::TOLERANCE_SECONDS) {
            return false;
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

        foreach ($parts['v1'] ?? [] as $candidate) {
            if (hash_equals($expected, $candidate)) {
                return true;
            }
        }

        return false;
    }
}
