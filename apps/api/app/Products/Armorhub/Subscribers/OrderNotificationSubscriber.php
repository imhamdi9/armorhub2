<?php

namespace App\Products\Armorhub\Subscribers;

use App\Core\Events\EventEnvelope;
use App\Core\Events\EventSubscriber;
use App\Models\OrderEvent;
use App\Services\OrderNotifier;

/** Turns order events into in-app and email notifications for the right people. */
class OrderNotificationSubscriber implements EventSubscriber
{
    public function id(): string
    {
        return 'armorhub.order-notifications';
    }

    public function handles(): array
    {
        return ['order.*'];
    }

    public function handle(EventEnvelope $event): void
    {
        $orderEvent = OrderEvent::find($event->payload['order_event_id']);

        if ($orderEvent) {
            app(OrderNotifier::class)->handle($orderEvent);
        }
    }
}
