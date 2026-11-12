<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class CustomRoleTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
    }

    public function test_only_platform_admins_manage_roles(): void
    {
        foreach ([$this->customer, $this->manager, $this->tech] as $nobody) {
            $this->actingAs($nobody, 'sanctum')->getJson('/api/admin/roles')->assertForbidden();
            $this->postJson('/api/admin/roles', ['name' => 'viewer', 'permissions' => []])->assertForbidden();
        }
    }

    public function test_a_custom_role_gets_only_what_it_was_given_and_never_admin_powers(): void
    {
        $this->actingAs($this->admin, 'sanctum');
        $this->postJson('/api/admin/roles', ['name' => 'product-viewer', 'permissions' => ['products.view', 'dashboard.view']])->assertCreated();

        // Escalation attempts are refused: admin, destructive and money-moving permissions are not grantable.
        foreach (['admin.panel', 'users.manage', 'products.delete', 'commissions.pay', 'payments.create', 'tenants.manage'] as $forbidden) {
            $this->postJson('/api/admin/roles', ['name' => 'sneaky', 'permissions' => [$forbidden]])->assertStatus(422);
        }
        // Built-in names are reserved and cannot be recreated.
        $this->postJson('/api/admin/roles', ['name' => 'platform-admin', 'permissions' => []])->assertStatus(422);
        $this->postJson('/api/admin/roles', ['name' => 'Bad Name', 'permissions' => []])->assertStatus(422);

        $password = 'Str0ng-passw0rd!x';
        $company = $this->customer->company_id ?? $this->manager->company_id;
        $this->postJson('/api/users', ['name' => 'Vera', 'email' => 'vera@test.com', 'password' => $password, 'role' => 'product-viewer'])->assertStatus(422); // needs a company
        $id = $this->postJson('/api/users', ['name' => 'Vera', 'email' => 'vera@test.com', 'password' => $password, 'role' => 'product-viewer', 'company_id' => $company])->assertCreated()->json('id');

        $vera = \App\Models\User::findOrFail($id);
        $this->actingAs($vera, 'sanctum');
        $this->getJson('/api/me')->assertOk()->assertJsonCount(2, 'permissions');
        $this->assertEqualsCanonicalizing(['dashboard.view', 'products.view'], $this->getJson('/api/me')->json('permissions'));
        $this->getJson('/api/products')->assertOk();                              // given
        $this->postJson('/api/products', ['name' => 'x'])->assertForbidden();     // not given
        $this->getJson('/api/admin/overview')->assertForbidden();
        $this->getJson('/api/admin/roles')->assertForbidden();
        $this->getJson('/api/users')->assertForbidden();
    }

    public function test_editing_and_deleting_rules(): void
    {
        $this->actingAs($this->admin, 'sanctum');
        $role = $this->postJson('/api/admin/roles', ['name' => 'orders-reader', 'permissions' => ['orders.view']])->assertCreated()->json('id');
        $this->putJson("/api/admin/roles/{$role}", ['permissions' => ['orders.view', 'products.view']])->assertOk();

        $roles = collect($this->getJson('/api/admin/roles')->assertOk()->json('roles'));
        $this->assertEqualsCanonicalizing(['orders.view', 'products.view'], $roles->firstWhere('name', 'orders-reader')['permissions']);
        $this->assertTrue($roles->firstWhere('name', 'platform-admin')['built_in']);

        // Built-in roles are untouchable.
        $builtIn = $roles->firstWhere('name', 'technician')['id'];
        $this->putJson("/api/admin/roles/{$builtIn}", ['permissions' => ['products.update']])->assertForbidden();
        $this->deleteJson("/api/admin/roles/{$builtIn}")->assertForbidden();

        // A role in use cannot be deleted; an unused one can.
        $company = $this->manager->company_id;
        $this->postJson('/api/users', ['name' => 'Ola', 'email' => 'ola@test.com', 'password' => 'Str0ng-passw0rd!x', 'role' => 'orders-reader', 'company_id' => $company])->assertCreated();
        $this->deleteJson("/api/admin/roles/{$role}")->assertStatus(422);
        $empty = $this->postJson('/api/admin/roles', ['name' => 'unused-role', 'permissions' => []])->json('id');
        $this->deleteJson("/api/admin/roles/{$empty}")->assertNoContent();
    }
}
