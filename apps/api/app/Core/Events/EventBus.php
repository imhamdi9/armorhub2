<?php

namespace App\Core\Events;

use App\Core\Events\Models\DeliveryRecord;
use App\Core\Events\Models\EventRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Publishes domain events. The event and the list of deliveries are written in the caller's transaction (transactional
 * outbox), then delivered after it commits: an event is never lost if the change committed and never sent if it rolled back.
 * The transport is deliberately this database table; a message broker can replace it behind the same interface.
 */
class EventBus
{
    /** The event being handled right now, so events published by a subscriber are linked back to it. */
    private ?EventEnvelope $current = null;

    public function __construct(private EventCatalog $catalog, private SubscriberRegistry $subscribers)
    {
    }

    /**
     * @throws UnknownEvent
     * @throws InvalidEventPayload
     */
    public function publish(Event $event): EventEnvelope
    {
        $definition = $this->catalog->require($event->name);
        $definition->validate($event->payload);

        $record = DB::transaction(function () use ($event, $definition) {
            $record = EventRecord::create([
                'event_id' => (string) Str::uuid(), 'name' => $event->name, 'version' => $definition->version, 'tenant_id' => $event->tenantId,
                'aggregate_type' => $event->aggregateType, 'aggregate_id' => $event->aggregateId !== null ? (string) $event->aggregateId : null,
                'payload' => $event->payload, 'actor_type' => $event->actorType, 'actor_id' => $event->actorId !== null ? (string) $event->actorId : null,
                'correlation_id' => $this->current?->correlationId ?? (string) Str::uuid(), 'causation_id' => $this->current?->id,
                'source' => $definition->owner, 'occurred_at' => now(),
            ]);

            foreach ($this->subscribers->matching($event->name) as $subscriber) {
                DeliveryRecord::create(['event_id' => $record->id, 'subscriber' => $subscriber, 'status' => 'pending']);
            }

            return $record;
        });

        // After the outermost transaction commits (immediately when there is none).
        DB::afterCommit(fn () => app(DeliveryProcessor::class)->process($record->id));

        return $record->toEnvelope();
    }

    /** Runs a handler "inside" an event so anything it publishes carries the same correlation and points back to it. */
    public function within(EventEnvelope $event, callable $callback): mixed
    {
        $previous = $this->current;
        $this->current = $event;

        try {
            return $callback();
        } finally {
            $this->current = $previous;
        }
    }

    /**
     * Sends an event again: to every subscriber, or just one. Used after fixing a subscriber, or to rebuild a projection.
     *
     * @return int deliveries reset
     */
    public function replay(string $eventId, ?string $subscriber = null): int
    {
        $record = EventRecord::where('event_id', $eventId)->firstOrFail();

        $count = DeliveryRecord::where('event_id', $record->id)->when($subscriber, fn ($q) => $q->where('subscriber', $subscriber))
            ->update(['status' => 'pending', 'attempts' => 0, 'next_attempt_at' => null, 'last_error' => null, 'delivered_at' => null]);

        app(DeliveryProcessor::class)->process($record->id);

        return $count;
    }
}
