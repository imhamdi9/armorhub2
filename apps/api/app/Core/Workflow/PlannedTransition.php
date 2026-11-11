<?php

namespace App\Core\Workflow;

/** A transition that has passed every check but is not yet applied: the caller persists the new state, then runs the actions. */
final class PlannedTransition
{
    public function __construct(
        public readonly WorkflowDefinition $definition,
        public readonly Transition $transition,
        public readonly string $from,
        public readonly string $to,
        public readonly WorkflowContext $context,
        private readonly WorkflowRegistry $registry,
    ) {
    }

    public function event(): ?string
    {
        return $this->transition->event;
    }

    public function runActions(): void
    {
        foreach ($this->transition->actions as $name) {
            $this->registry->action($name)->run($this->context);
        }
    }
}
