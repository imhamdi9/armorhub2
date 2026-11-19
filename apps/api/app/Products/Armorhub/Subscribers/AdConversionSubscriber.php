<?php

namespace App\Products\Armorhub\Subscribers;

use App\Core\Events\EventEnvelope;
use App\Core\Events\EventSubscriber;
use App\Models\Order;
use App\Services\Marketing\AdConversions;

/** A paid order is reported to the market's ad platforms (when the buyer allowed marketing). Safe to run twice: each platform gets an event id once. */
class AdConversionSubscriber implements EventSubscriber
{
    public function id(): string
    {
        return 'armorhub.ad-conversions';
    }

    public function handles(): array
    {
        return ['order.paid', 'order.refunded', 'order.partially_refunded'];
    }

    public function handle(EventEnvelope $event): void
    {
        $order = Order::withoutGlobalScopes()->with('customer:id,email,contact_email')->find($event->payload['order_id']);
        if (! $order) {
            return;
        }
        if ($event->name === 'order.paid') {
            app(AdConversions::class)->purchase($order);

            return;
        }
        // Money given back: the whole payable amount (less any part already refunded), or the part named in the event.
        $amount = (float) ($event->payload['data']['amount'] ?? 0);
        if ($amount > 0) {
            app(AdConversions::class)->refund($order, $amount, (int) ($event->payload['order_event_id'] ?? 0));
        }
    }
}
