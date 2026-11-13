<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\IndustrySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** The platform admin is never turned away from a screen or action of the API (only built-in roles stay protected, and signed links need their signature). */
class AdminHasAllPowersTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_route_answers_the_platform_admin_with_forbidden(): void
    {
        $this->seed([IndustrySeeder::class, RolesAndPermissionsSeeder::class]);
        $tenant = Tenant::create(['name' => 'ArmorHub Egypt', 'industry_id' => \App\Models\Industry::first()->id, 'market' => 'EG']);
        $admin = User::factory()->create();
        $admin->assignRole('platform-admin');
        $this->actingAs($admin, 'sanctum');

        $allowed403 = ['api/email/verify/{id}/{hash}', 'api/admin/roles/{id}'];
        $forbidden = [];
        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/') || in_array($route->uri(), $allowed403, true)) {
                continue;
            }
            $path = '/'.preg_replace('/\{[^}]+\}/', '1', $route->uri()).(str_starts_with($route->uri(), 'api/market') ? '?market='.$tenant->id : '');
            foreach (array_diff($route->methods(), ['HEAD', 'OPTIONS']) as $method) {
                if ($this->json($method, $path, $method === 'GET' ? [] : ['x' => 1])->status() === 403) {
                    $forbidden[] = "{$method} {$route->uri()}";
                }
            }
        }

        $this->assertSame([], $forbidden, 'The admin was refused here');
    }

    public function test_the_admin_holds_every_permission_that_exists(): void
    {
        $this->seed([IndustrySeeder::class, RolesAndPermissionsSeeder::class]);
        $admin = User::factory()->create();
        $admin->assignRole('platform-admin');

        foreach (\Spatie\Permission\Models\Permission::pluck('name') as $permission) {
            $this->assertTrue($admin->can($permission), "admin lacks {$permission}");
        }
        $this->assertTrue($admin->can('some.future.permission'));
    }
}
