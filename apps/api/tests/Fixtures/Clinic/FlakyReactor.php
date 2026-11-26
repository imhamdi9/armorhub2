<?php

namespace Tests\Fixtures\Clinic;

use App\Core\Events\EventEnvelope;
use App\Core\Events\EventSubscriber;

/** Fails until told to behave: shows retry, dead-letter and replay. */
class FlakyReactor implements EventSubscriber
{
    public static bool $broken = false;

    public static int $calls = 0;

    public function id(): string
    {
        return 'clinic.flaky';
    }

    public function handles(): array
    {
        return ['clinic.*'];
    }

    public function handle(EventEnvelope $event): void
    {
        self::$calls++;

        if (self::$broken) {
            throw new \RuntimeException('Downstream system is unavailable');
        }
    }
}
