<?php

namespace App\Core\Modules;

use App\Core\Events\EventCatalog;
use App\Core\Events\EventDefinition;
use App\Core\Events\SubscriberRegistry;

/** The extension points a module may use. Adding a new kind of extension means adding a method here, once. */
class ModuleContext
{
    public function __construct(private string $moduleId, private EventCatalog $events, private SubscriberRegistry $subscribers)
    {
    }

    /**
     * Declare an event this module owns.
     *
     * @param array<string, string> $schema field => type (int|string|bool|float|array|mixed, "?" = optional)
     */
    public function event(string $name, array $schema = [], string $description = '', int $version = 1): void
    {
        $this->events->register(new EventDefinition($name, $this->moduleId, $schema, $description, $version));
    }

    /** @param class-string<\App\Core\Events\EventSubscriber> $subscriber */
    public function subscribe(string $subscriber): void
    {
        $this->subscribers->register($subscriber, $this->moduleId);
    }
}
