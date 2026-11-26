<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class WorkflowAdminTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
        $this->tenant = Tenant::first();
    }

    private function customised(callable $mutate): array
    {
        $def = $this->actingAs($this->admin, 'sanctum')->getJson("/api/admin/platform/workflows?tenant_id={$this->tenant->id}")->json('workflows.0.default');

        return $mutate($def);
    }

    public function test_only_platform_admins_manage_workflows(): void
    {
        foreach ([$this->customer, $this->manager, $this->tech] as $nobody) {
            $this->actingAs($nobody, 'sanctum')->getJson('/api/admin/platform/workflows')->assertForbidden();
            $this->putJson("/api/admin/platform/tenants/{$this->tenant->id}/workflows/armorhub.order", ['definition' => []])->assertForbidden();
            $this->deleteJson("/api/admin/platform/tenants/{$this->tenant->id}/workflows/armorhub.order")->assertForbidden();
        }
    }

    public function test_the_admin_sees_the_order_workflow_as_data(): void
    {
        $wf = $this->actingAs($this->admin, 'sanctum')->getJson("/api/admin/platform/workflows?tenant_id={$this->tenant->id}")->assertOk()->json('workflows.0');

        $this->assertSame('armorhub.order', $wf['key']);
        $this->assertSame('armorhub', $wf['owner']);
        $this->assertFalse($wf['customised']);
        $this->assertSame('partner_selected', $wf['default']['transitions']['accept_quote']['to']);
        $this->assertSame(['armorhub.grant_warranty'], $wf['default']['transitions']['complete']['actions']);
    }

    public function test_a_market_changes_how_orders_behave_by_configuration_alone(): void
    {
        $paid = $this->order();
        $this->actingAs($this->customer, 'sanctum')->postJson("/api/orders/{$paid}/cancel")->assertUnprocessable()
            ->assertJsonPath('errors.order.0', 'Paid orders cannot be cancelled here; please contact support.');

        // This market decides customers may cancel a paid order. No code changes: one stored definition.
        $custom = $this->customised(function ($def) {
            $def['transitions']['cancel']['from'][] = 'paid';
            $def['transitions']['cancel']['message'] = 'Not possible any more.';

            return $def;
        });
        $this->putJson("/api/admin/platform/tenants/{$this->tenant->id}/workflows/armorhub.order", ['definition' => $custom])->assertOk()->assertJsonPath('version', 1);
        $this->assertSame(1, AuditLog::where('action', 'platform.workflow_customised')->count());

        $this->actingAs($this->customer, 'sanctum')->postJson("/api/orders/{$paid}/cancel", ['reason' => 'Changed my mind'])->assertOk()->assertJsonPath('status', 'cancelled');
        $this->assertSame(OrderStatus::Cancelled, Order::withoutGlobalScopes()->find($paid)->status);

        // A newer version replaces it; reverting returns to the product's behaviour.
        $again = $this->order();
        $this->actingAs($this->admin, 'sanctum')->putJson("/api/admin/platform/tenants/{$this->tenant->id}/workflows/armorhub.order", ['definition' => $custom])->assertOk()->assertJsonPath('version', 2);
        $this->deleteJson("/api/admin/platform/tenants/{$this->tenant->id}/workflows/armorhub.order")->assertOk()->assertJsonPath('customised', false);
        $this->actingAs($this->customer, 'sanctum')->postJson("/api/orders/{$again}/cancel")->assertUnprocessable();
    }

    public function test_invalid_customisations_are_refused_with_reasons(): void
    {
        $this->actingAs($this->admin, 'sanctum');

        $newState = $this->customised(function ($def) {
            $def['states']['refunded'] = ['label' => 'Refunded'];

            return $def;
        });
        $this->putJson("/api/admin/platform/tenants/{$this->tenant->id}/workflows/armorhub.order", ['definition' => $newState])->assertUnprocessable()->assertJsonValidationErrors('definition');

        $unknownAction = $this->customised(function ($def) {
            $def['transitions']['complete']['actions'] = ['nope.nothing'];

            return $def;
        });
        $this->putJson("/api/admin/platform/tenants/{$this->tenant->id}/workflows/armorhub.order", ['definition' => $unknownAction])->assertUnprocessable();

        $this->putJson("/api/admin/platform/tenants/{$this->tenant->id}/workflows/no.such", ['definition' => ['x' => 1]])->assertNotFound();
    }

    public function test_a_different_market_keeps_the_default_behaviour(): void
    {
        $other = Tenant::create(['name' => 'Other market']);
        $custom = $this->customised(function ($def) {
            $def['transitions']['cancel']['from'][] = 'paid';

            return $def;
        });
        $this->putJson("/api/admin/platform/tenants/{$other->id}/workflows/armorhub.order", ['definition' => $custom])->assertOk();

        // The customer in the marketplace tenant is unaffected by another market's choice.
        $paid = $this->order();
        $this->actingAs($this->customer, 'sanctum')->postJson("/api/orders/{$paid}/cancel")->assertUnprocessable();
    }
}
