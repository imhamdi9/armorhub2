<?php

namespace App\Core\Workflow;

/** A side effect that runs, inside the caller's transaction, after a transition is applied. Must be safe to roll back with it. */
interface Action
{
    public function name(): string;

    public function run(WorkflowContext $context): void;
}
