<?php

namespace App\Core\Workflow;

/** One allowed move between states, with the checks that must pass (guards) and the side effects to run (actions). */
final class Transition
{
    /**
     * @param list<string>|'*' $from     states it may start from, or '*' for any
     * @param list<string>     $guards   names of registered guards
     * @param list<string>     $actions  names of registered actions
     */
    public function __construct(
        public readonly string $name,
        public readonly array|string $from,
        public readonly string $to,
        public readonly array $guards = [],
        public readonly array $actions = [],
        public readonly ?string $event = null,
        public readonly ?string $message = null,
        public readonly string $label = '',
    ) {
    }

    public function allowsFrom(string $state): bool
    {
        return $this->from === '*' || in_array($state, (array) $this->from, true);
    }

    public function toArray(): array
    {
        return ['from' => $this->from, 'to' => $this->to, 'guards' => $this->guards, 'actions' => $this->actions, 'event' => $this->event, 'message' => $this->message, 'label' => $this->label];
    }
}
