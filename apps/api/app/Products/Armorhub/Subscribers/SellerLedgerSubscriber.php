<?php

namespace App\Products\Armorhub\Subscribers;

use App\Core\Events\EventEnvelope;
use App\Core\Events\EventSubscriber;
use App\Models\Commission;
use App\Models\Order;
use App\Models\SellerLedgerEntry;

/**
 * Keeps each seller's ledger from the order events alone. A paid sale is held (the buyer may still not receive the goods);
 * delivery releases it. Safe to run twice: entries are unique per order and type.
 */
class SellerLedgerSubscriber implements EventSubscriber
{
    public function id(): string
    {
        return 'armorhub.seller-ledger';
    }

    public function handles(): array
    {
        return ['order.paid', 'order.shipment_delivered'];
    }

    public function handle(EventEnvelope $event): void
    {
        $order = Order::withoutGlobalScopes()->with('product:id,seller_id')->find($event->payload['order_id']);
        $sellerId = $order?->product?->seller_id;

        if (! $sellerId) {
            return;     // a service job, not a seller's sale
        }

        if ($event->name === 'order.paid') {
            $commission = (float) (Commission::where('order_id', $order->id)->value('amount') ?? 0);

            SellerLedgerEntry::firstOrCreate(['order_id' => $order->id, 'type' => 'sale'], ['company_id' => $sellerId, 'amount' => (float) $order->total_amount, 'currency' => $order->currency, 'status' => 'held']);
            SellerLedgerEntry::firstOrCreate(['order_id' => $order->id, 'type' => 'commission'], ['company_id' => $sellerId, 'amount' => -$commission, 'currency' => $order->currency, 'status' => 'held']);

            return;
        }

        SellerLedgerEntry::where('order_id', $order->id)->where('status', 'held')->update(['status' => 'available', 'available_at' => now()]);
    }
}
