<?php

namespace Tests\Fixtures\Clinic;

use App\Core\Events\Event;
use App\Core\Events\EventBus;
use App\Core\Events\EventEnvelope;
use App\Core\Events\EventSubscriber;

/** Reacts to a booking by scheduling a reminder: an event published from inside a subscriber. */
class BookingReactor implements EventSubscriber
{
    /** @var list<EventEnvelope> */
    public static array $seen = [];

    public function id(): string
    {
        return 'clinic.booking-reactor';
    }

    public function handles(): array
    {
        return ['clinic.appointment_booked'];
    }

    public function handle(EventEnvelope $event): void
    {
        self::$seen[] = $event;

        app(EventBus::class)->publish(new Event('clinic.reminder_scheduled', ['appointment_id' => $event->payload['appointment_id']], $event->tenantId, 'appointment', $event->payload['appointment_id']));
    }
}
