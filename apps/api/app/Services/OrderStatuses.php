<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\InstallationRequest;
use App\Models\Order;
use App\Models\Payment;
use App\Models\SellerLedgerEntry;
use App\Models\Shipment;

/**
 * One order, five questions with five separate answers: where is the order, the payment, the goods, the service and the settlement?
 * The goods can be shipped while the fitting has not started, and a seller can be paid while a refund is open on another part. Each answer is
 * worked out from the records of its own part, so none of them has to wait for, or lie about, the others. Parts an order does not have are null.
 */
class OrderStatuses
{
    /** @return array{order: string, payment: string, product: ?string, service: ?string, settlement: ?string} */
    public function of(Order $order): array
    {
        $status = $order->status instanceof OrderStatus ? $order->status : OrderStatus::tryFrom((string) $order->status);

        return [
            'order' => $this->order($status),
            'payment' => $this->payment($order, $status),
            'product' => $this->product($order),
            'service' => $this->service($order, $status),
            'settlement' => $this->settlement($order),
        ];
    }

    /** What the customer sees: everything but the seller's settlement, which is between the seller and the platform. */
    public function forCustomer(Order $order): array
    {
        return array_diff_key($this->of($order), ['settlement' => 1]);
    }

    /** Draft, pending payment, confirmed, completed, cancelled. */
    private function order(?OrderStatus $s): string
    {
        return match ($s) {
            OrderStatus::Cancelled => 'cancelled',
            OrderStatus::Completed => 'completed',
            OrderStatus::Paid, OrderStatus::AppointmentScheduled, OrderStatus::ServiceInProgress => 'confirmed',
            OrderStatus::AwaitingPayment, OrderStatus::PartnerSelected => 'pending_payment',
            default => 'draft',   // still being quoted
        };
    }

    /** Pending, paid, failed (expired), refunded. */
    private function payment(Order $order, ?OrderStatus $s): string
    {
        $payments = Payment::withoutGlobalScopes()->where('order_id', $order->id)->pluck('status');
        if ($order->refund_status) {
            return 'refunded';
        }
        if ($payments->contains('completed')) {
            return 'paid';
        }
        if ($payments->contains('cod_due')) {
            return 'cash_on_delivery';
        }
        if ($s === OrderStatus::Cancelled && $payments->isEmpty()) {
            return 'not_required';
        }

        return $payments->contains('expired') && ! $payments->contains('pending') ? 'failed' : 'pending';
    }

    /** Only for goods: pending, processing, ready, shipped, delivered, returned. */
    private function product(Order $order): ?string
    {
        if ($order->refund_status) {
            return 'cancelled';
        }

        $goods = $order->product_id && (bool) \App\Models\Product::withoutGlobalScopes()->whereKey($order->product_id)->value('requires_shipping');
        if (! $goods) {
            return null;
        }

        $shipments = Shipment::where('order_id', $order->id)->pluck('status');
        if ($shipments->isNotEmpty()) {
            return match (true) {
                $shipments->contains(Shipment::RETURNED) => 'returned',
                $shipments->every(fn ($s) => in_array($s, [Shipment::DELIVERED], true)) => 'delivered',
                $shipments->every(fn ($s) => $s === Shipment::CANCELLED) => 'cancelled',
                $shipments->contains(Shipment::CREATED) && ! $shipments->contains(Shipment::IN_TRANSIT) && ! $shipments->contains(Shipment::OUT_FOR_DELIVERY) => 'ready',
                default => 'shipped',
            };
        }

        $status = $order->status instanceof OrderStatus ? $order->status : OrderStatus::tryFrom((string) $order->status);

        return match ($status) {
            OrderStatus::Cancelled => 'cancelled',
            OrderStatus::Paid, OrderStatus::AppointmentScheduled, OrderStatus::ServiceInProgress, OrderStatus::Completed => 'processing',
            default => 'pending',
        };
    }

    /** For service orders (a partner's quote) and for an installation linked to the order: requested, quoted, accepted, booked, in progress, completed. */
    private function service(Order $order, ?OrderStatus $s): ?string
    {
        $job = InstallationRequest::where('order_id', $order->id)->latest('id')->first();
        if ($job) {
            return match ($job->status) {
                'open' => 'requested',
                'offers_received' => 'quoted',
                'selected', 'awaiting_payment' => 'accepted',
                'paid' => 'booked',
                'in_progress', 'proof_submitted' => 'in_progress',
                'released' => 'completed',
                'refunded', 'cancelled', 'expired' => 'cancelled',
                default => 'requested',
            };
        }

        $isService = $order->product_id && ! (bool) \App\Models\Product::withoutGlobalScopes()->whereKey($order->product_id)->value('requires_shipping');
        if (! $isService) {
            return null;
        }

        return match ($s) {
            OrderStatus::PendingQuotes => 'requested',
            OrderStatus::QuotesReceived => 'quoted',
            OrderStatus::PartnerSelected, OrderStatus::AwaitingPayment => 'accepted',
            OrderStatus::Paid, OrderStatus::AppointmentScheduled => 'booked',
            OrderStatus::ServiceInProgress => 'in_progress',
            OrderStatus::Completed => 'completed',
            OrderStatus::Cancelled => 'cancelled',
            default => 'requested',
        };
    }

    /** What the seller is owed on this order: pending (held), eligible (available), paid. Null when no seller is paid through the ledger. */
    private function settlement(Order $order): ?string
    {
        $entries = SellerLedgerEntry::where('order_id', $order->id)->get();
        if ($entries->isEmpty()) {
            return null;
        }

        return match (true) {
            $entries->every(fn ($e) => $e->status === 'paid') => 'paid',
            $entries->contains(fn ($e) => $e->status === 'available' && $e->payout_id !== null) => 'processing',
            $entries->contains(fn ($e) => $e->status === 'available') => 'eligible',
            default => 'pending',
        };
    }
}
