<?php

namespace App\Core\Workflow;

use App\Core\Workflow\Models\WorkflowOverride;

/** Gives a market its own version of a workflow (validated), or takes it back. Used by the admin panel and by blueprints. */
class WorkflowCustomiser
{
    public function __construct(private WorkflowRegistry $registry)
    {
    }

    /** @return list<string> reasons it was refused (empty = saved) */
    public function apply(int $tenantId, string $key, array $definition, ?int $by = null, ?int &$version = null): array
    {
        if ($errors = $this->registry->overrideErrors($key, $definition)) {
            return $errors;
        }

        $version = (int) WorkflowOverride::where('workflow_key', $key)->where('tenant_id', $tenantId)->max('version') + 1;
        WorkflowOverride::where('workflow_key', $key)->where('tenant_id', $tenantId)->update(['active' => false]);
        WorkflowOverride::create(['workflow_key' => $key, 'tenant_id' => $tenantId, 'version' => $version, 'definition' => $definition, 'active' => true, 'created_by' => $by]);
        $this->registry->forget();

        return [];
    }

    public function reset(int $tenantId, string $key): void
    {
        WorkflowOverride::where('workflow_key', $key)->where('tenant_id', $tenantId)->update(['active' => false]);
        $this->registry->forget();
    }
}
