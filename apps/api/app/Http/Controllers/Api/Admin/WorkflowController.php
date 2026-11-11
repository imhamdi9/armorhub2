<?php

namespace App\Http\Controllers\Api\Admin;

use App\Core\Workflow\Models\WorkflowOverride;
use App\Core\Workflow\WorkflowRegistry;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Workflows as an operator sees them: what each product ships with, and each market's own version of it. */
class WorkflowController extends Controller
{
    public function index(Request $request, WorkflowRegistry $workflows)
    {
        $request->validate(['tenant_id' => 'nullable|integer|exists:tenants,id']);
        $tenantId = $request->filled('tenant_id') ? $request->integer('tenant_id') : null;

        return ['workflows' => collect($workflows->defaults())->map(function ($default, $key) use ($workflows, $tenantId) {
            $active = $workflows->forTenant($key, $tenantId);
            $override = $tenantId ? WorkflowOverride::where('workflow_key', $key)->where('tenant_id', $tenantId)->where('active', true)->orderByDesc('version')->first() : null;

            return ['key' => $key, 'owner' => $default->owner, 'default' => $default->toArray(), 'active' => $active->toArray(), 'customised' => (bool) $override, 'version' => $override?->version];
        })->values()];
    }

    /** A market re-wires the transitions between the states the product already has. */
    public function update(Request $request, Tenant $tenant, string $key, WorkflowRegistry $workflows)
    {
        abort_unless(isset($workflows->defaults()[$key]), 404);
        $definition = $request->validate(['definition' => 'required|array'])['definition'];

        if ($errors = $workflows->overrideErrors($key, $definition)) {
            throw ValidationException::withMessages(['definition' => $errors]);
        }

        $next = (int) WorkflowOverride::where('workflow_key', $key)->where('tenant_id', $tenant->id)->max('version') + 1;
        WorkflowOverride::where('workflow_key', $key)->where('tenant_id', $tenant->id)->update(['active' => false]);
        WorkflowOverride::create(['workflow_key' => $key, 'tenant_id' => $tenant->id, 'version' => $next, 'definition' => $definition, 'active' => true, 'created_by' => $request->user()->id]);
        $workflows->forget();
        Audit::record('platform.workflow_customised', $tenant, ['workflow' => $key, 'version' => $next]);

        return ['workflow' => $key, 'version' => $next];
    }

    /** Back to the product's own definition. Earlier versions are kept for the record. */
    public function reset(Tenant $tenant, string $key, WorkflowRegistry $workflows)
    {
        abort_unless(isset($workflows->defaults()[$key]), 404);

        WorkflowOverride::where('workflow_key', $key)->where('tenant_id', $tenant->id)->update(['active' => false]);
        $workflows->forget();
        Audit::record('platform.workflow_reset', $tenant, ['workflow' => $key]);

        return ['workflow' => $key, 'customised' => false];
    }
}
