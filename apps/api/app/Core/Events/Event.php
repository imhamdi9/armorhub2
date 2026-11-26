<?php

namespace App\Core\Events;

/** An event about to be published. Names are catalogued; the payload must match the catalogued contract. */
final class Event
{
    public function __construct(
        public readonly string $name,
        public readonly array $payload = [],
        public readonly ?int $tenantId = null,
        public readonly ?string $aggregateType = null,
        public readonly int|string|null $aggregateId = null,
        public readonly ?string $actorType = null,
        public readonly int|string|null $actorId = null,
    ) {
    }
}
