<?php

namespace App\Core\Workflow;

/**
 * A workflow as data: its states and the transitions between them. Anything product-specific is referenced by name
 * (guards and actions), so a definition can be stored, versioned, validated and edited without code.
 */
final class WorkflowDefinition
{
    /**
     * @param array<string, array{label?: string, final?: bool}> $states
     * @param array<string, Transition>                           $transitions
     */
    public function __construct(
        public readonly string $key,
        public readonly string $initial,
        public readonly array $states,
        public readonly array $transitions,
        public readonly int $version = 1,
        public readonly string $owner = '',
    ) {
        if (! preg_match('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/', $key)) {
            throw new \InvalidArgumentException("Workflow key [{$key}] must look like module.name.");
        }

        $errors = $this->structureErrors();
        if ($errors) {
            throw new \InvalidArgumentException("Workflow [{$key}] is invalid: ".implode(' ', $errors));
        }
    }

    /** @return list<string> */
    public function structureErrors(): array
    {
        $errors = [];

        if (! isset($this->states[$this->initial])) {
            $errors[] = "The initial state [{$this->initial}] is not a state.";
        }

        foreach ($this->transitions as $name => $t) {
            $starts = $t->from === '*' || $t->from === ['*'] ? [] : (array) $t->from;

            foreach ($starts as $from) {
                if (! isset($this->states[$from])) {
                    $errors[] = "Transition [{$name}] starts from unknown state [{$from}].";
                }
            }
            if (! isset($this->states[$t->to])) {
                $errors[] = "Transition [{$name}] goes to unknown state [{$t->to}].";
            }
        }

        return $errors;
    }

    public function isFinal(string $state): bool
    {
        return (bool) ($this->states[$state]['final'] ?? false);
    }

    /** @return array<string, Transition> the transitions that may start from this state */
    public function from(string $state): array
    {
        return array_filter($this->transitions, fn (Transition $t) => $t->allowsFrom($state));
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key, 'version' => $this->version, 'initial' => $this->initial, 'states' => $this->states,
            'transitions' => array_map(fn (Transition $t) => $t->toArray(), $this->transitions),
        ];
    }

    public static function fromArray(array $data, string $owner = ''): self
    {
        $transitions = [];
        foreach ($data['transitions'] ?? [] as $name => $t) {
            $transitions[$name] = new Transition(
                $name, $t['from'] ?? '*', $t['to'] ?? '', (array) ($t['guards'] ?? []), (array) ($t['actions'] ?? []),
                $t['event'] ?? null, $t['message'] ?? null, $t['label'] ?? '',
            );
        }

        return new self($data['key'] ?? '', $data['initial'] ?? '', $data['states'] ?? [], $transitions, (int) ($data['version'] ?? 1), $owner);
    }
}
