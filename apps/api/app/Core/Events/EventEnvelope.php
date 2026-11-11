<?php

namespace App\Core\Events;

use Carbon\CarbonInterface;

/** What a subscriber receives: the event plus everything needed to trace and isolate it. Immutable. */
final class EventEnvelope
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly int $version,
        public readonly ?int $tenantId,
        public readonly array $payload,
        public readonly CarbonInterface $occurredAt,
        public readonly string $correlationId,
        public readonly ?string $causationId,
        public readonly ?string $actorType,
        public readonly ?string $actorId,
        public readonly ?string $aggregateType,
        public readonly ?string $aggregateId,
        public readonly ?string $source,
    ) {
    }
}
