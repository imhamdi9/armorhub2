<?php

namespace App\Core\Blueprints\Steps;

use App\Core\Blueprints\BlueprintContext;
use App\Core\Blueprints\BlueprintError;
use App\Core\Blueprints\BlueprintStepHandler;
use App\Core\Workflow\WorkflowCustomiser;
use App\Core\Workflow\WorkflowRegistry;

/**
 * "workflow.override": give the blueprint's market its own version of a workflow. Args: key, and either a full
 * definition or a small set of changes ("changes": [{"transition": "cancel", "from": [...]}]) applied on the default.
 */
class WorkflowOverrideStep implements BlueprintStepHandler
{
    public function __construct(private WorkflowCustomiser $customiser, private WorkflowRegistry $registry)
    {
    }

    public function type(): string
    {
        return 'workflow.override';
    }

    public function apply(array $args, BlueprintContext $context): array
    {
        $tenantId = $context->tenantId ?? throw new BlueprintError('A step before "workflow.override" must create the market.');
        $key = (string) ($args['key'] ?? throw new BlueprintError('workflow.override needs a key.'));
        $definition = $args['definition'] ?? $this->registry->default($key)->toArray();

        foreach ($args['changes'] ?? [] as $change) {
            $name = $change['transition'] ?? throw new BlueprintError('Each change needs a transition.');
            if (! isset($definition['transitions'][$name])) {
                throw new BlueprintError("Workflow [{$key}] has no transition [{$name}].");
            }
            foreach (array_diff_key($change, ['transition' => 1]) as $field => $value) {
                $definition['transitions'][$name][$field] = $value;
            }
        }

        $version = null;
        if ($errors = $this->customiser->apply($tenantId, $key, $definition, $context->actorId, $version)) {
            throw new BlueprintError(implode(' ', $errors));
        }

        return ['workflow' => $key, 'version' => $version];
    }
}
