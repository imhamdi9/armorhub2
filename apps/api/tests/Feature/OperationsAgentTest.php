<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** The operations agent sees how the business is doing and works orders, but cannot touch money or settings. */
class OperationsAgentTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
        $this->agent = User::factory()->create(['company_id' => Company::withoutGlobalScopes()->where('name', 'Store')->value('id')]);
        $this->agent->assignRole('operations-agent');
    }

    public function test_the_role_exists_with_its_own_permission(): void
    {
        $role = Role::findByName('operations-agent');
        $this->assertTrue($role->hasPermissionTo('operations.manage'));
        $this->assertFalse($role->hasPermissionTo('admin.panel'));
        $this->assertFalse($role->hasPermissionTo('market.settings'));
    }

    public function test_an_operations_agent_reads_the_business_numbers_but_cannot_refund_or_manage_zones(): void
    {
        $this->actingAs($this->agent, 'sanctum');
        $this->getJson('/api/admin/kpis')->assertOk()->assertJsonStructure(['gmv', 'pilot']);
        $this->postJson('/api/admin/orders/1/refund', ['reason' => 'not mine to do'])->assertForbidden();
        $this->getJson('/api/admin/zones')->assertForbidden();
        $this->getJson('/api/admin/payments')->assertForbidden();
    }

    public function test_a_customer_cannot_read_the_numbers(): void
    {
        $this->actingAs($this->customer, 'sanctum')->getJson('/api/admin/kpis')->assertForbidden();
    }
}
