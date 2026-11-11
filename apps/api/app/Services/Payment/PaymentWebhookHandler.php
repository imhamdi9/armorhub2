<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Models\Payment;
use App\Support\Audit;

/** Acts on a verified provider webhook, whichever provider sent it. */
class PaymentWebhookHandler
{
    public function __construct(private PaymentFinalizer $finalizer)
    {
    }

    public function handle(string $provider, PaymentEvent $event): void
    {
        match ($event->type) {
            'paid' => $this->paid($provider, $event),
            'expired' => Payment::withoutGlobalScopes()
                ->where('transaction_id', $event->reference)->where('status', 'pending')->update(['status' => 'expired']),
        };
    }

    private function paid(string $provider, PaymentEvent $event): void
    {
        $order = $event->orderId ? Order::withoutGlobalScopes()->find($event->orderId) : null;

        if (! $order) {
            return;
        }

        // Never trust the event alone: what was paid must match what the order costs.
        $expected = Money::minorUnits($order->total_amount, $order->currency);
        $paid = $event->amountMinor ?? ($event->amountMajor !== null ? Money::minorUnits($event->amountMajor, $order->currency) : null);

        if ($paid !== $expected || ($event->currency !== null && strtoupper($event->currency) !== strtoupper($order->currency))) {
            Audit::record('payments.webhook_mismatch', $order, [
                'provider' => $provider, 'expected_minor' => $expected, 'paid_minor' => $paid, 'currency' => $event->currency,
            ]);
            report(new \RuntimeException("Payment amount mismatch for order {$order->id} via {$provider}."));

            return;
        }

        $this->finalizer->complete($order, ['transaction_id' => $event->reference, 'provider' => $provider], 'card');
    }
}
