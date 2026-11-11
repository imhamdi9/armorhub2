<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Partner;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\IndustrySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class StaffTest extends TestCase
{
    use RefreshDatabase;

    private Company $acme;
    private Company $other;
    private Partner $shop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IndustrySeeder::class, RolesAndPermissionsSeeder::class]);

        $tenant = Tenant::create(['name' => 'T']);
        $this->acme = Company::create(['tenant_id' => $tenant->id, 'name' => 'Acme']);
        $this->other = Company::create(['tenant_id' => $tenant->id, 'name' => 'Other']);
        $this->shop = Partner::create(['company_id' => $this->acme->id, 'name' => 'Shop', 'city' => 'X', 'country' => 'SA']);
    }

    private function user(string $role, ?Company $company = null, ?Partner $partner = null, array $extra = []): User
    {
        $user = User::factory()->create($extra + ['company_id' => $company?->id, 'partner_id' => $partner?->id]);
        $user->assignRole($role);

        return $user;
    }

    private function payload(string $role, array $over = []): array
    {
        return $over + ['name' => 'New Person', 'email' => uniqid().'@example.com', 'password' => 'longenough1', 'role' => $role];
    }

    public function test_admin_creates_any_staff_member_who_can_then_sign_in(): void
    {
        $admin = $this->user('platform-admin');

        $payload = $this->payload('partner-manager', [
            'email' => 'manager@example.com', 'company_id' => $this->acme->id, 'partner_id' => $this->shop->id,
        ]);
        $this->actingAs($admin, 'sanctum')->postJson('/api/users', $payload)
            ->assertCreated()->assertJsonPath('role', 'partner-manager')->assertJsonPath('partner.name', 'Shop');

        $this->postJson('/api/login', ['email' => 'manager@example.com', 'password' => 'longenough1'])->assertOk();
    }

    public function test_company_owner_is_confined_to_their_company_and_roles(): void
    {
        $owner = $this->user('company-owner', $this->acme);
        $this->actingAs($owner, 'sanctum');

        // Creates staff in their own company, regardless of what company_id they send.
        $id = $this->postJson('/api/users', $this->payload('technician', ['company_id' => $this->other->id]))
            ->assertCreated()->assertJsonPath('company.id', $this->acme->id)->json('id');
        $this->assertSame($this->acme->id, User::find($id)->company_id);

        // Cannot create admins or customers.
        $this->postJson('/api/users', $this->payload('platform-admin'))->assertUnprocessable();
        $this->postJson('/api/users', $this->payload('customer'))->assertUnprocessable();

        // Partner must be inside their company.
        $foreign = Partner::create(['company_id' => $this->other->id, 'name' => 'Foreign', 'city' => 'X', 'country' => 'SA']);
        $this->postJson('/api/users', $this->payload('partner-manager', ['partner_id' => $foreign->id]))->assertUnprocessable();
        $this->postJson('/api/users', $this->payload('partner-manager', ['partner_id' => $this->shop->id]))->assertCreated();

        // Only sees their own company's people.
        $outsider = $this->user('technician', $this->other);
        $list = collect($this->getJson('/api/users')->assertOk()->json('data'))->pluck('id');
        $this->assertFalse($list->contains($outsider->id));
        $this->putJson("/api/users/{$outsider->id}", ['name' => 'Hacked'])->assertNotFound();
        $this->deleteJson("/api/users/{$outsider->id}")->assertNotFound();
    }

    public function test_partner_manager_can_only_add_technicians_to_their_own_partner(): void
    {
        $manager = $this->user('partner-manager', $this->acme, $this->shop);
        $this->actingAs($manager, 'sanctum');

        $this->postJson('/api/users', $this->payload('technician', ['partner_id' => 999]))
            ->assertUnprocessable(); // the partner must exist...
        $id = $this->postJson('/api/users', $this->payload('technician'))
            ->assertCreated()->assertJsonPath('partner.id', $this->shop->id)->json('id');
        $this->assertSame($this->shop->id, User::find($id)->partner_id);

        $this->postJson('/api/users', $this->payload('partner-manager'))->assertUnprocessable();
        $this->postJson('/api/users', $this->payload('company-owner'))->assertUnprocessable();
    }

    public function test_nobody_can_escalate_or_lock_themselves_out(): void
    {
        $owner = $this->user('company-owner', $this->acme);
        $peer = $this->user('company-owner', $this->acme);
        $this->actingAs($owner, 'sanctum');

        $this->putJson("/api/users/{$owner->id}", ['role' => 'technician'])->assertUnprocessable();
        $this->putJson("/api/users/{$owner->id}", ['is_active' => false])->assertUnprocessable();
        $this->deleteJson("/api/users/{$owner->id}")->assertUnprocessable();
        $this->putJson("/api/users/{$owner->id}", ['role' => 'platform-admin'])->assertUnprocessable();

        // A company owner cannot touch a platform admin who somehow shares the company.
        $admin = $this->user('platform-admin', $this->acme);
        $this->putJson("/api/users/{$admin->id}", ['name' => 'x'])->assertForbidden();
        $this->deleteJson("/api/users/{$admin->id}")->assertForbidden();

        // Peers are manageable.
        $this->putJson("/api/users/{$peer->id}", ['role' => 'technician', 'name' => 'Peer'])->assertOk()->assertJsonPath('role', 'technician');
    }

    public function test_deactivating_blocks_login_and_revokes_tokens(): void
    {
        $admin = $this->user('platform-admin');
        $worker = $this->user('technician', $this->acme, $this->shop, ['password' => 'longenough1']);
        $token = $worker->createToken('t')->plainTextToken;

        $this->actingAs($admin, 'sanctum')->putJson("/api/users/{$worker->id}", ['is_active' => false])->assertOk();

        $this->assertSame(0, $worker->tokens()->count());
        $this->postJson('/api/login', ['email' => $worker->email, 'password' => 'longenough1'])->assertUnprocessable();

        $this->putJson("/api/users/{$worker->id}", ['is_active' => true])->assertOk();
        $this->postJson('/api/login', ['email' => $worker->email, 'password' => 'longenough1'])->assertOk();
    }

    public function test_customers_and_technicians_cannot_manage_users(): void
    {
        foreach (['customer', 'technician'] as $role) {
            $this->actingAs($this->user($role, $this->acme), 'sanctum')
                ->getJson('/api/users')->assertForbidden();
        }
        $this->getJson('/api/users')->assertForbidden();
    }

    public function test_password_reset_flow(): void
    {
        Notification::fake();
        $user = $this->user('customer', $this->acme, null, ['email' => 'forgetful@example.com']);
        $token = $user->createToken('old')->plainTextToken;

        // Same answer whether or not the email exists.
        $this->postJson('/api/forgot-password', ['email' => 'forgetful@example.com'])->assertOk();
        $this->postJson('/api/forgot-password', ['email' => 'ghost@example.com'])->assertOk();
        Notification::assertSentTo($user, ResetPassword::class, function ($n) use ($user) {
            $url = $n->toMail($user)->actionUrl;
            $this->assertStringStartsWith('http://localhost:3000/reset-password?token=', $url);
            $this->assertStringContainsString('email=forgetful%40example.com', $url);

            return true;
        });
        Notification::assertCount(1);

        $good = Password::createToken($user);
        $this->postJson('/api/reset-password', [
            'token' => 'wrong', 'email' => 'forgetful@example.com',
            'password' => 'brandnewpass1', 'password_confirmation' => 'brandnewpass1',
        ])->assertUnprocessable();

        $this->postJson('/api/reset-password', [
            'token' => $good, 'email' => 'forgetful@example.com',
            'password' => 'brandnewpass1', 'password_confirmation' => 'brandnewpass1',
        ])->assertOk();

        $this->assertSame(0, $user->tokens()->count());
        $this->postJson('/api/login', ['email' => 'forgetful@example.com', 'password' => 'brandnewpass1'])->assertOk();
    }
}
