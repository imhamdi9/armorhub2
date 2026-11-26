<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/**
 * Which role may open which screen's data. A role is listed under "allow" only when its screen needs the route, and under "deny" when the
 * route must stay closed to it: this is what keeps a menu item and its API in step, and stops a permission from leaking to a role that
 * has no business with it (a marketing manager reading orders, a technician reading a manager's price requests).
 */
class RolePermissionMatrixTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private const ROLES = ['platform-admin', 'company-owner', 'partner-manager', 'technician', 'customer', 'seller', 'warehouse-staff', 'operations-agent', 'support-agent', 'catalog-reviewer', 'marketing-manager'];

    /** @var array<string, array{allow: list<string>, deny: list<string>}> */
    private const MATRIX = [
        '/api/admin/markets' => ['allow' => ['platform-admin'], 'deny' => ['company-owner', 'partner-manager', 'technician', 'customer', 'seller', 'support-agent', 'catalog-reviewer', 'marketing-manager']],
        '/api/admin/roles' => ['allow' => ['platform-admin'], 'deny' => ['company-owner', 'partner-manager', 'technician', 'customer', 'seller', 'support-agent', 'marketing-manager']],
        '/api/admin/marketing/settings' => ['allow' => ['platform-admin', 'marketing-manager'], 'deny' => ['partner-manager', 'technician', 'customer', 'seller', 'support-agent', 'catalog-reviewer', 'warehouse-staff']],
        '/api/admin/product-reviews' => ['allow' => ['platform-admin', 'catalog-reviewer'], 'deny' => ['partner-manager', 'technician', 'customer', 'seller', 'support-agent', 'marketing-manager', 'warehouse-staff']],
        '/api/admin/kpis' => ['allow' => ['platform-admin', 'operations-agent'], 'deny' => ['technician', 'customer', 'seller', 'catalog-reviewer', 'marketing-manager', 'warehouse-staff']],
        '/api/partner/orders' => ['allow' => ['partner-manager'], 'deny' => ['technician', 'customer', 'seller', 'support-agent', 'catalog-reviewer', 'marketing-manager', 'warehouse-staff', 'operations-agent']],
        '/api/products' => ['allow' => ['platform-admin', 'company-owner', 'operations-agent'], 'deny' => ['support-agent', 'marketing-manager']],
    ];

    private function userWith(string $role): User
    {
        if ($role === 'platform-admin') {
            return $this->admin;
        }
        if ($role === 'partner-manager') {
            return $this->manager;
        }
        if ($role === 'technician') {
            return $this->tech;
        }
        if ($role === 'customer') {
            return $this->customer;
        }

        return tap(User::factory()->create(['company_id' => $this->customer->company_id]), fn (User $u) => $u->assignRole($role));
    }

    public function test_every_screen_is_open_to_the_roles_that_need_it_and_closed_to_the_others(): void
    {
        $this->buildMarketplace();
        $problems = [];

        foreach (self::MATRIX as $uri => $rule) {
            foreach (self::ROLES as $role) {
                if (! in_array($role, $rule['allow'], true) && ! in_array($role, $rule['deny'], true)) {
                    continue;
                }
                $this->app['auth']->forgetGuards();
                $this->actingAs($this->userWith($role), 'sanctum');
                $status = $this->getJson($uri.(str_contains($uri, 'marketing') ? '?market=1' : ''))->getStatusCode();
                $open = ! in_array($status, [401, 403], true);

                if (in_array($role, $rule['allow'], true) && ! $open) {
                    $problems[] = "{$role} should open {$uri} but got {$status}";
                }
                if (in_array($role, $rule['deny'], true) && $open) {
                    $problems[] = "{$role} must not open {$uri} but got {$status}";
                }
            }
        }

        $this->assertSame([], $problems, implode("\n", $problems));
    }
}
