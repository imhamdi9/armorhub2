<?php

namespace App\Core\Events;

use App\Core\Events\Models\DeliveryRecord;
use App\Core\Modules\TenantModules;

/**
 * Runs pending deliveries: calls each subscriber, records the outcome, schedules retries with backoff and
 * dead-letters what keeps failing. A failing subscriber never affects the code that published the event.
 */
class DeliveryProcessor
{
    /** Seconds to wait after the 1st, 2nd, ... failed attempt. */
    private const BACKOFF = [10, 60, 300, 1800, 7200];

    public function __construct(private SubscriberRegistry $subscribers, private TenantModules $modules, private EventBus $bus)
    {
    }

    /** @return int how many deliveries were attempted */
    public function process(?int $eventRowId = null, int $limit = 100): int
    {
        $due = DeliveryRecord::with('event')
            ->whereIn('status', ['pending', 'failed'])
            ->where(fn ($q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
            ->when($eventRowId, fn ($q) => $q->where('event_id', $eventRowId))
            ->orderBy('id')->limit($limit)->get();

        $done = 0;

        foreach ($due as $delivery) {
            // Claim it atomically so two workers never run the same delivery.
            $claimed = DeliveryRecord::whereKey($delivery->id)->whereIn('status', ['pending', 'failed'])->update(['status' => 'processing']);
            if (! $claimed) {
                continue;
            }

            // Re-read it so the model knows its status is now "processing" (otherwise a failed -> failed change looks like no change).
            $this->run($delivery->refresh());
            $done++;
        }

        return $done;
    }

    private function run(DeliveryRecord $delivery): void
    {
        $envelope = $delivery->event->toEnvelope();
        $subscriber = $this->subscribers->find($delivery->subscriber);

        if (! $subscriber) {
            $delivery->update(['status' => 'dead', 'last_error' => 'The subscriber is no longer registered.']);

            return;
        }

        // A module a tenant switched off does not receive that tenant's events.
        if (! $this->modules->enabled($envelope->tenantId, (string) $this->subscribers->moduleOf($delivery->subscriber))) {
            $delivery->update(['status' => 'skipped', 'last_error' => 'The module is disabled for this tenant.']);

            return;
        }

        $attempts = $delivery->attempts + 1;

        try {
            $this->bus->within($envelope, fn () => $subscriber->handle($envelope));
            $delivery->update(['status' => 'delivered', 'attempts' => $attempts, 'delivered_at' => now(), 'last_error' => null, 'next_attempt_at' => null]);
        } catch (\Throwable $e) {
            report($e);
            $dead = $attempts >= (int) config('events.max_attempts', 6);

            $delivery->update([
                'status' => $dead ? 'dead' : 'failed', 'attempts' => $attempts, 'last_error' => mb_substr($e->getMessage(), 0, 1000),
                'next_attempt_at' => $dead ? null : now()->addSeconds(self::BACKOFF[min($attempts, count(self::BACKOFF)) - 1]),
            ]);
        }
    }
}
