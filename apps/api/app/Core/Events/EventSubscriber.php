<?php

namespace App\Core\Events;

/**
 * Something that reacts to events: a notification, a projection, an integration. Delivery is at-least-once,
 * so handle() must be idempotent. Throwing marks the delivery failed and schedules a retry.
 */
interface EventSubscriber
{
    /** Stable identifier, e.g. "armorhub.order-notifications". Part of the delivery record: never rename it. */
    public function id(): string;

    /** Event name patterns, e.g. ["order.*"] or ["clinic.appointment_booked"]. */
    public function handles(): array;

    public function handle(EventEnvelope $event): void;
}
