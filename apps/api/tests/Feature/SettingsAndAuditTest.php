<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Settings\Settings;
use Database\Seeders\IndustrySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SettingsAndAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IndustrySeeder::class, RolesAndPermissionsSeeder::class]);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('platform-admin');
    }

    private function user(string $role): User
    {
        $u = User::factory()->create();
        $u->assignRole($role);

        return $u;
    }

    public function test_defaults_apply_until_an_admin_changes_them(): void
    {
        $settings = app(Settings::class);

        $this->assertEquals(10.0, $settings->get('platform.commission_percentage'));
        $this->assertSame('USD', $settings->get('platform.currency'));
        $this->assertTrue($settings->bool('platform.registration_open'));
        $this->assertFalse($settings->isCustomised('platform.currency'));

        $settings->set('platform.currency', 'sar', $this->admin);

        $this->assertSame('SAR', app(Settings::class)->get('platform.currency'));
        $this->assertTrue($settings->isCustomised('platform.currency'));

        $settings->forget('platform.currency');
        $this->assertSame('USD', $settings->get('platform.currency'));
    }

    public function test_changed_commission_and_currency_drive_real_orders_and_payouts(): void
    {
        $settings = app(Settings::class);
        $settings->set('platform.commission_percentage', 20);
        $settings->set('platform.currency', 'SAR');

        $calc = app(\App\Services\Commission\CalculateCommissionService::class)->calculate(1000);
        $this->assertSame(200.0, $calc['amount']);
        $this->assertSame(20.0, $calc['percentage']);
    }

    public function test_only_platform_admins_can_use_the_settings_api(): void
    {
        foreach (['customer', 'company-owner', 'partner-manager', 'technician'] as $role) {
            $this->actingAs($this->user($role), 'sanctum')->getJson('/api/admin/settings')->assertForbidden();
            $this->putJson('/api/admin/settings', ['values' => ['platform.currency' => 'EUR']])->assertForbidden();
            // A market admin reads the log of their own market's people; nobody else but the platform admin reads it.
            $role === 'company-owner' ? $this->getJson('/api/admin/audit-logs')->assertOk() : $this->getJson('/api/admin/audit-logs')->assertForbidden();
        }
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/admin/settings')->assertUnauthorized();
    }

    public function test_admins_edit_settings_with_validation(): void
    {
        $this->actingAs($this->admin, 'sanctum');

        $this->putJson('/api/admin/settings', ['values' => ['platform.currency' => 'eur', 'security.max_open_orders' => 7]])->assertOk();
        $this->assertSame('EUR', app(Settings::class)->get('platform.currency'));
        $this->assertSame(7, app(Settings::class)->get('security.max_open_orders'));

        $this->putJson('/api/admin/settings', ['values' => ['platform.currency' => 'dollars']])->assertUnprocessable();
        $this->putJson('/api/admin/settings', ['values' => ['security.max_open_orders' => 0]])->assertUnprocessable();
        $this->putJson('/api/admin/settings', ['values' => ['platform.commission_percentage' => 150]])->assertUnprocessable();
        $this->putJson('/api/admin/settings', ['values' => ['security.captcha_provider' => 'evil']])->assertUnprocessable();
        $this->putJson('/api/admin/settings', ['values' => ['nope.setting' => 1]])->assertUnprocessable();

        // The three commission shares must total 1.
        $this->putJson('/api/admin/settings', ['values' => ['platform.commission_split.platform' => 0.9]])->assertUnprocessable();
        $this->putJson('/api/admin/settings', ['values' => [
            'platform.commission_split.platform' => 0.5, 'platform.commission_split.company' => 0.2, 'platform.commission_split.partner' => 0.3,
        ]])->assertOk();

        $this->deleteJson('/api/admin/settings/security.max_open_orders')->assertOk();
        $this->assertSame(5, app(Settings::class)->get('security.max_open_orders'));
        $this->deleteJson('/api/admin/settings/bogus')->assertNotFound();
    }

    public function test_secrets_are_encrypted_masked_and_never_logged(): void
    {
        $this->actingAs($this->admin, 'sanctum');

        $this->putJson('/api/admin/settings', ['values' => ['security.captcha_secret' => 'super-secret-key-123']])->assertOk();

        // Stored encrypted: the plain value is nowhere in the table.
        $raw = DB::table('settings')->where('key', 'security.captcha_secret')->value('value');
        $this->assertStringNotContainsString('super-secret-key-123', $raw);
        $this->assertTrue(Setting::where('key', 'security.captcha_secret')->value('is_secret'));
        $this->assertSame('super-secret-key-123', app(Settings::class)->get('security.captcha_secret'));

        // Never returned by the API, only "is set".
        $body = $this->getJson('/api/admin/settings?group=security')->assertOk()->getContent();
        $this->assertStringNotContainsString('super-secret-key-123', $body);
        $row = collect($this->getJson('/api/admin/settings')->json('settings'))->firstWhere('key', 'security.captcha_secret');
        $this->assertNull($row['value']);
        $this->assertTrue($row['has_value']);

        // Never written to the audit log.
        $this->assertStringNotContainsString('super-secret-key-123', json_encode(AuditLog::all()->toArray()));
        $this->assertStringContainsString('(secret changed)', json_encode(AuditLog::where('action', 'settings.updated')->get()->toArray()));

        // Leaving the field blank or masked keeps the secret; the model cannot leak it either.
        $this->putJson('/api/admin/settings', ['values' => ['security.captcha_secret' => Settings::MASK]])->assertOk();
        $this->putJson('/api/admin/settings', ['values' => ['security.captcha_secret' => '']])->assertOk();
        $this->assertSame('super-secret-key-123', app(Settings::class)->get('security.captcha_secret'));
        $this->assertArrayNotHasKey('value', Setting::first()->toArray());
    }

    public function test_a_changed_app_key_makes_secrets_unset_instead_of_crashing(): void
    {
        app(Settings::class)->set('security.captcha_secret', 'abc');
        DB::table('settings')->where('key', 'security.captcha_secret')->update(['value' => 'not-decryptable']);
        app(Settings::class)->flush();

        $this->assertNull(app(Settings::class)->get('security.captcha_secret'));
    }

    public function test_the_audit_log_records_changes_and_cannot_be_edited(): void
    {
        $tenant = Tenant::create(['name' => 'Acme']);
        $tenant->update(['name' => 'Acme Ltd']);
        $user = $this->user('customer');
        $user->update(['password' => 'a-new-password-1']);

        $actions = AuditLog::pluck('action')->all();
        $this->assertContains('tenant.created', $actions);
        $this->assertContains('tenant.updated', $actions);
        $this->assertContains('user.updated', $actions);

        $update = AuditLog::where('action', 'tenant.updated')->first();
        $this->assertSame('Acme Ltd', $update->meta['name']);
        $this->assertSame('Tenant', $update->subject_type);

        // Passwords are noted but never recorded.
        $this->assertStringNotContainsString('a-new-password-1', json_encode(AuditLog::all()->toArray()));
        $this->assertSame('(changed)', AuditLog::where('action', 'user.updated')->latest('id')->first()->meta['password']);

        // Append-only.
        $this->assertFalse($update->update(['action' => 'tampered']));
        $this->assertSame('tenant.updated', $update->fresh()->action);
    }

    public function test_the_audit_log_api_filters_and_attributes_actions_to_the_actor(): void
    {
        $this->actingAs($this->admin, 'sanctum');
        $tenantId = $this->postJson('/api/tenants', ['name' => 'Logged Co', 'industry_id' => \App\Models\Industry::first()->id])->assertCreated()->json('id');

        $row = collect($this->getJson('/api/admin/audit-logs?action=tenant.created')->assertOk()->json('data'))->first();
        $this->assertSame($this->admin->id, $row['user']['id']);
        $this->assertSame("Tenant#{$tenantId}", $row['subject']);
        $this->assertNotNull($row['ip']);

        $this->getJson('/api/admin/audit-logs?action=nothing.like.this')->assertJsonCount(0, 'data');
        $this->getJson('/api/admin/audit-logs?search=Logged')->assertJsonCount(1, 'data');
        $this->getJson('/api/admin/audit-logs?per_page=500')->assertUnprocessable();
    }

    public function test_audit_prune_removes_only_old_entries(): void
    {
        AuditLog::create(['action' => 'old.thing']);
        AuditLog::create(['action' => 'new.thing']);
        AuditLog::where('action', 'old.thing')->toBase()->update(['created_at' => now()->subDays(400)]);

        $this->artisan('audit:prune', ['--days' => 365])->assertSuccessful();

        $this->assertSame(0, AuditLog::where('action', 'old.thing')->count());
        $this->assertSame(1, AuditLog::where('action', 'new.thing')->count());
    }

    public function test_the_shared_cache_never_holds_a_decrypted_secret(): void
    {
        $settings = app(Settings::class);
        $settings->set('security.captcha_secret', 'plaintext-must-not-be-cached', $this->admin);
        $settings->set('platform.currency', 'EUR', $this->admin);

        // A fresh read fills the cache; the secret is still readable through the service...
        $fresh = new Settings($settings->registry());
        $this->assertSame('plaintext-must-not-be-cached', $fresh->get('security.captcha_secret'));
        $this->assertSame('EUR', $fresh->get('platform.currency'));

        // ...but what sits in the cache store is only the encrypted form.
        $cached = \Illuminate\Support\Facades\Cache::get('platform.settings.v1');
        $this->assertNotNull($cached);
        $this->assertStringNotContainsString('plaintext-must-not-be-cached', json_encode($cached));
        $this->assertTrue($cached['security.captcha_secret']['secret']);
    }

    public function test_settings_fall_back_to_defaults_when_the_database_is_not_ready(): void
    {
        \Illuminate\Support\Facades\Schema::drop('settings');
        \Illuminate\Support\Facades\Cache::flush();

        $settings = new Settings(app(\App\Support\Settings\SettingsRegistry::class));

        $this->assertSame('USD', $settings->get('platform.currency'));
        $this->assertTrue($settings->bool('platform.registration_open'));

        // ...and the security checklist (used by a CLI command) still runs.
        $this->artisan('platform:security-check')->assertSuccessful();
    }
}
