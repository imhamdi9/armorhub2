<?php

namespace App\Core\Events;

/** Every event type the platform knows, with its owner and contract. Modules add to it; nothing else may. */
class EventCatalog
{
    /** @var array<string, EventDefinition> */
    private array $definitions = [];

    public function register(EventDefinition $definition): void
    {
        if (isset($this->definitions[$definition->name]) && $this->definitions[$definition->name]->owner !== $definition->owner) {
            throw new \LogicException("Event [{$definition->name}] is already owned by [{$this->definitions[$definition->name]->owner}].");
        }

        $this->definitions[$definition->name] = $definition;
    }

    /** @throws UnknownEvent */
    public function require(string $name): EventDefinition
    {
        return $this->definitions[$name] ?? throw new UnknownEvent("Event [{$name}] is not in the catalog. Declare it in a module.");
    }

    public function has(string $name): bool
    {
        return isset($this->definitions[$name]);
    }

    /** @return array<string, EventDefinition> */
    public function all(): array
    {
        ksort($this->definitions);

        return $this->definitions;
    }
}
