<?php

namespace App\Core\Blueprints;

class BlueprintRegistry
{
    /** @var array<string, Blueprint> */
    private array $blueprints = [];

    /** @var array<string, BlueprintStepHandler> */
    private array $handlers = [];

    public function register(Blueprint $blueprint): void
    {
        $this->blueprints[$blueprint->id] = $blueprint;
    }

    public function registerHandler(BlueprintStepHandler $handler): void
    {
        $this->handlers[$handler->type()] = $handler;
    }

    public function get(string $id): ?Blueprint
    {
        return $this->blueprints[$id] ?? null;
    }

    public function handler(string $type): BlueprintStepHandler
    {
        return $this->handlers[$type] ?? throw new BlueprintError("No handler for step type [{$type}].");
    }

    /** @return array<string, Blueprint> */
    public function all(): array
    {
        ksort($this->blueprints);

        return $this->blueprints;
    }
}
