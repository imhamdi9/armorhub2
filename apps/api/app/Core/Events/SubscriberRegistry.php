<?php

namespace App\Core\Events;

/** The subscribers modules registered, and which module owns each. */
class SubscriberRegistry
{
    /** @var array<string, array{subscriber: class-string<EventSubscriber>, module: string}> */
    private array $subscribers = [];

    /** @param class-string<EventSubscriber> $class */
    public function register(string $class, string $module): void
    {
        $id = app($class)->id();

        if (isset($this->subscribers[$id]) && $this->subscribers[$id]['subscriber'] !== $class) {
            throw new \LogicException("Subscriber id [{$id}] is already used by [{$this->subscribers[$id]['subscriber']}].");
        }

        $this->subscribers[$id] = ['subscriber' => $class, 'module' => $module];
    }

    public function find(string $id): ?EventSubscriber
    {
        return isset($this->subscribers[$id]) ? app($this->subscribers[$id]['subscriber']) : null;
    }

    public function moduleOf(string $id): ?string
    {
        return $this->subscribers[$id]['module'] ?? null;
    }

    /** Subscribers whose patterns match this event name. @return list<string> subscriber ids */
    public function matching(string $eventName): array
    {
        $ids = [];

        foreach ($this->subscribers as $id => $entry) {
            foreach (app($entry['subscriber'])->handles() as $pattern) {
                if (fnmatch($pattern, $eventName)) {
                    $ids[] = $id;
                    break;
                }
            }
        }

        return $ids;
    }

    /** @return array<string, array{subscriber: string, module: string, handles: list<string>}> */
    public function all(): array
    {
        $out = [];
        foreach ($this->subscribers as $id => $entry) {
            $out[$id] = $entry + ['handles' => app($entry['subscriber'])->handles()];
        }

        return $out;
    }
}
