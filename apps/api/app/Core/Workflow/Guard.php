<?php

namespace App\Core\Workflow;

/** A check that must pass before a transition. Return true to allow, or a string explaining why not. */
interface Guard
{
    public function name(): string;

    public function check(WorkflowContext $context): bool|string;
}
