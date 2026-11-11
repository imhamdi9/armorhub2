<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Payment\PaymentGateway;
use App\Services\Payment\SimulatedGateway;
use Database\Seeders\IndustrySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class HardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IndustrySeeder::class, RolesAndPermissionsSeeder::class]);
    }

    private function user(string $role, ?Company $company = null): User
    {
        $user = User::factory()->create(['company_id' => $company?->id]);
        $user->assignRole($role);

        return $user;
    }

    public function test_login_is_rate_limited(): void
    {
        RateLimiter::clear('auth');

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/login', ['email' => 'a@b.com', 'password' => 'wrong'])->assertUnprocessable();
        }

        $this->postJson('/api/login', ['email' => 'a@b.com', 'password' => 'wrong'])->assertStatus(429);
    }

    public function test_company_management(): void
    {
        $tenant = Tenant::create(['name' => 'T']);
        $admin = $this->user('platform-admin');

        $id = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/companies', ['tenant_id' => $tenant->id, 'name' => 'Acme', 'type' => 'partner'])
            ->assertCreated()->assertJsonPath('type', 'partner')->json('id');

        $this->putJson("/api/companies/{$id}", ['status' => 'active', 'name' => 'Acme Ltd'])
            ->assertOk()->assertJsonPath('status', 'active');

        // A company owner can edit their own company, but not its type/status, and cannot create or delete.
        $company = Company::find($id);
        $owner = $this->user('company-owner', $company);
        $this->actingAs($owner, 'sanctum')
            ->putJson("/api/companies/{$id}", ['phone' => '555', 'type' => 'platform', 'status' => 'suspended'])
            ->assertOk()->assertJsonPath('phone', '555');
        $fresh = $company->fresh();
        $this->assertSame('partner', $fresh->type);
        $this->assertSame('active', $fresh->status);

        $this->postJson('/api/companies', ['tenant_id' => $tenant->id, 'name' => 'Nope'])->assertForbidden();
        $this->deleteJson("/api/companies/{$id}")->assertForbidden();

        $this->actingAs($admin, 'sanctum')->deleteJson("/api/companies/{$id}")->assertOk();
    }

    public function test_simulated_payments_are_refused_in_production(): void
    {
        $this->assertInstanceOf(SimulatedGateway::class, app(PaymentGateway::class));

        $this->app['env'] = 'production';

        $this->expectException(\App\Services\Payment\PaymentUnavailable::class);
        app(PaymentGateway::class)->charge(new \App\Models\Order(), 10.0);
    }

    public function test_simulated_payments_can_be_explicitly_allowed_in_production(): void
    {
        $this->app['env'] = 'production';
        config(['platform.allow_simulated_payments_in_production' => true]);

        $result = app(PaymentGateway::class)->charge(new \App\Models\Order(), 10.0);

        $this->assertSame('completed', $result['status']);
        $this->assertStringStartsWith('SIM-', $result['transaction_id']);
    }

    public function test_create_admin_command(): void
    {
        $this->artisan('platform:create-admin', ['email' => 'boss@example.com', '--password' => 'longenough1'])
            ->expectsOutputToContain('created')->assertSuccessful();

        $this->assertTrue(User::where('email', 'boss@example.com')->first()->hasRole('platform-admin'));

        $this->artisan('platform:create-admin', ['email' => 'boss@example.com', '--password' => 'longenough1'])->assertFailed();
        $this->artisan('platform:create-admin', ['email' => 'x@example.com', '--password' => 'short'])->assertFailed();
    }

    public function test_create_admin_if_missing_is_idempotent(): void
    {
        $args = ['email' => 'boss@example.com', '--password' => 'longenough1', '--if-missing' => true];

        $this->artisan('platform:create-admin', $args)->assertSuccessful();
        $this->artisan('platform:create-admin', $args)->expectsOutputToContain('already exists')->assertSuccessful();

        $this->assertSame(1, User::where('email', 'boss@example.com')->count());
    }

    public function test_demo_command_builds_a_working_marketplace(): void
    {
        $this->artisan('platform:demo')->assertSuccessful();

        $this->assertTrue(User::where('email', 'shop@test.com')->first()->hasRole('partner-manager'));
        $this->postJson('/api/login', ['email' => 'admin@test.com', 'password' => 'password123'])->assertOk();
    }
}
