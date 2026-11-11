<?php

namespace App\Services\Shipping;

use Carbon\CarbonInterface;

/** One tracking update from a carrier, already translated to the platform's statuses. */
final class TrackingEvent
{
    public function __construct(
        public readonly string $status,
        public readonly ?string $trackingNumber = null,
        public readonly ?string $reference = null,
        public readonly ?string $description = null,
        public readonly ?string $location = null,
        public readonly ?CarbonInterface $occurredAt = null,
    ) {
    }
}
