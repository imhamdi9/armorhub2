<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** Working for a market is not enough to read its customers' orders: only roles that deal with orders do. */
class OrderVisibilityByRoleTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private function staff(string $role): User
    {
        return tap(User::factory()->create(['company_id' => $this->customer->company_id]), fn ($u) => $u->assignRole($role));
    }

    public function test_roles_that_deal_with_orders_see_them_and_the_others_see_nothing(): void
    {
        $this->buildMarketplace();
        $id = $this->order(false);

        foreach (['support-agent', 'operations-agent', 'warehouse-staff'] as $role) {
            $this->app['auth']->forgetGuards();
            $this->actingAs($this->staff($role), 'sanctum');
            $this->assertGreaterThan(0, count($this->getJson('/api/orders')->assertOk()->json('data')), "{$role} should see the market's orders");
            $this->getJson("/api/orders/{$id}")->assertOk();
        }
        foreach (['catalog-reviewer', 'marketing-manager'] as $role) {
            $this->app['auth']->forgetGuards();
            $this->actingAs($this->staff($role), 'sanctum');
            $this->assertSame([], $this->getJson('/api/orders')->assertOk()->json('data'), "{$role} must not see orders");
            $this->getJson("/api/orders/{$id}")->assertForbidden();
        }
    }
}
