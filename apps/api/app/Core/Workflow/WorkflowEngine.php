<?php

namespace App\Core\Workflow;

/** Decides whether a move is allowed and what it leads to. It never touches the data: the caller owns persistence. */
class WorkflowEngine
{
    public function __construct(private WorkflowRegistry $registry)
    {
    }

    /** @throws TransitionNotAllowed */
    public function plan(string $workflowKey, string $current, string $transition, WorkflowContext $context): PlannedTransition
    {
        $definition = $this->registry->forTenant($workflowKey, $context->tenantId);
        $t = $definition->transitions[$transition] ?? throw new TransitionNotAllowed("The step [{$transition}] does not exist.");

        if (! $t->allowsFrom($current)) {
            throw new TransitionNotAllowed($t->message ?? 'This step is not available in the current state.');
        }

        $context->transition = $transition;
        $context->from = $current;
        $context->to = $t->to;

        foreach ($t->guards as $name) {
            $result = $this->registry->guard($name)->check($context);

            if ($result !== true) {
                throw new TransitionNotAllowed(is_string($result) && $result !== '' ? $result : ($t->message ?? 'This step is not allowed.'));
            }
        }

        return new PlannedTransition($definition, $t, $current, $t->to, $context, $this->registry);
    }

    /** The steps a user could take from here (without running guards, which need the subject). @return list<string> */
    public function available(string $workflowKey, ?int $tenantId, string $current): array
    {
        return array_keys($this->registry->forTenant($workflowKey, $tenantId)->from($current));
    }
}
