<?php

namespace App\Core\Workflow;

use App\Core\Workflow\Models\WorkflowOverride;

/** The workflows, guards and actions modules contributed, and each tenant's own versions of the workflows. */
class WorkflowRegistry
{
    /** @var array<string, WorkflowDefinition> */
    private array $definitions = [];

    /** @var array<string, Guard> */
    private array $guards = [];

    /** @var array<string, Action> */
    private array $actions = [];

    /** @var array<string, WorkflowDefinition|null> per-request cache of resolved tenant versions */
    private array $resolved = [];

    public function register(WorkflowDefinition $definition): void
    {
        if (isset($this->definitions[$definition->key]) && $this->definitions[$definition->key]->owner !== $definition->owner) {
            throw new \LogicException("Workflow [{$definition->key}] is already owned by [{$this->definitions[$definition->key]->owner}].");
        }

        $this->definitions[$definition->key] = $definition;
    }

    public function registerGuard(Guard $guard): void
    {
        $this->guards[$guard->name()] = $guard;
    }

    public function registerAction(Action $action): void
    {
        $this->actions[$action->name()] = $action;
    }

    public function guard(string $name): Guard
    {
        return $this->guards[$name] ?? throw new \LogicException("Guard [{$name}] is not registered.");
    }

    public function action(string $name): Action
    {
        return $this->actions[$name] ?? throw new \LogicException("Action [{$name}] is not registered.");
    }

    /** @return array<string, WorkflowDefinition> the module-contributed defaults */
    public function defaults(): array
    {
        ksort($this->definitions);

        return $this->definitions;
    }

    public function default(string $key): WorkflowDefinition
    {
        return $this->definitions[$key] ?? throw new \LogicException("Workflow [{$key}] is not registered.");
    }

    /** The version a tenant actually runs: its active override if it has one, otherwise the default. */
    public function forTenant(string $key, ?int $tenantId): WorkflowDefinition
    {
        $default = $this->default($key);

        if ($tenantId === null) {
            return $default;
        }

        $cacheKey = "{$key}:{$tenantId}";
        if (! array_key_exists($cacheKey, $this->resolved)) {
            $row = WorkflowOverride::where('workflow_key', $key)->where('tenant_id', $tenantId)->where('active', true)->orderByDesc('version')->first();
            $this->resolved[$cacheKey] = $row ? WorkflowDefinition::fromArray($row->definition + ['key' => $key], $default->owner) : null;
        }

        return $this->resolved[$cacheKey] ?? $default;
    }

    public function forget(): void
    {
        $this->resolved = [];
    }

    /**
     * Why a tenant's proposed version is not acceptable (empty = fine). A tenant may re-wire transitions between the
     * states the product already has, but not invent or remove states: existing records live in those states.
     *
     * @return list<string>
     */
    public function overrideErrors(string $key, array $data): array
    {
        $default = $this->default($key);

        try {
            $candidate = WorkflowDefinition::fromArray($data + ['key' => $key], $default->owner);
        } catch (\InvalidArgumentException $e) {
            return [$e->getMessage()];
        }

        $errors = [];

        $mine = array_keys($candidate->states);
        $theirs = array_keys($default->states);
        sort($mine);
        sort($theirs);

        if ($mine !== $theirs) {
            $errors[] = 'The set of states cannot change: existing records are in those states.';
        }
        if ($candidate->initial !== $default->initial) {
            $errors[] = 'The initial state cannot change.';
        }
        foreach ($candidate->transitions as $name => $t) {
            foreach ($t->guards as $guard) {
                if (! isset($this->guards[$guard])) {
                    $errors[] = "Transition [{$name}] uses an unknown guard [{$guard}].";
                }
            }
            foreach ($t->actions as $action) {
                if (! isset($this->actions[$action])) {
                    $errors[] = "Transition [{$name}] uses an unknown action [{$action}].";
                }
            }
        }

        return $errors;
    }
}
