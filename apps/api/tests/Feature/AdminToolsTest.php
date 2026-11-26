<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** What the platform administrator needs to run the platform: view-as, safe deletion, user filters, per-currency numbers, system health. */
class AdminToolsTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    /** Call as someone who holds a real token (not a test shortcut), so the token's name counts. */
    private function as(string $token)
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function tokenOf(User $user, string $name = 'auth-token'): string
    {
        return $user->createToken($name)->plainTextToken;
    }

    public function test_an_administrator_can_view_as_a_customer_read_only_and_leave(): void
    {
        $this->buildMarketplace();

        $start = $this->as($this->tokenOf($this->admin))->postJson("/api/admin/impersonate/{$this->customer->id}")->assertOk();
        $viewing = $start->json('token');
        $this->assertNotEmpty($viewing);

        // They see what the customer sees, and are told whom they really are.
        $me = $this->as($viewing)->getJson('/api/me')->assertOk();
        $me->assertJsonPath('id', $this->customer->id)->assertJsonPath('impersonated_by', $this->admin->name);
        $this->as($viewing)->getJson('/api/orders')->assertOk();

        // But nothing can be changed while viewing.
        $this->as($viewing)->postJson('/api/addresses', ['name' => 'X'])->assertForbidden();
        $this->as($viewing)->postJson('/api/logout')->assertForbidden();

        // Both ends are in the audit log.
        $this->assertTrue(AuditLog::where('action', 'admin.impersonation_started')->where('user_id', $this->admin->id)->exists());

        // Leaving ends that session and gives the administrator a fresh one.
        $back = $this->as($viewing)->postJson('/api/impersonation/exit')->assertOk();
        $this->assertSame($this->admin->id, $back->json('user.id'));
        $this->as($viewing)->getJson('/api/me')->assertUnauthorized();
        $this->as($back->json('token'))->getJson('/api/me')->assertOk()->assertJsonPath('impersonated_by', null);
        $this->assertTrue(AuditLog::where('action', 'admin.impersonation_ended')->exists());
    }

    public function test_only_a_platform_administrator_can_view_as_and_never_as_another_administrator(): void
    {
        $this->buildMarketplace();
        $support = tap(User::factory()->create(['company_id' => $this->customer->company_id]), fn ($u) => $u->assignRole('support-agent'));
        $other = tap(User::factory()->create(), fn ($u) => $u->assignRole('platform-admin'));

        $this->as($this->tokenOf($support))->postJson("/api/admin/impersonate/{$this->customer->id}")->assertForbidden();
        $this->as($this->tokenOf($this->manager))->postJson("/api/admin/impersonate/{$this->customer->id}")->assertForbidden();
        $this->as($this->tokenOf($this->admin))->postJson("/api/admin/impersonate/{$other->id}")->assertUnprocessable();
        $this->as($this->tokenOf($this->admin))->postJson("/api/admin/impersonate/{$this->admin->id}")->assertUnprocessable();

        $this->customer->update(['is_active' => false]);
        $this->as($this->tokenOf($this->admin))->postJson("/api/admin/impersonate/{$this->customer->id}")->assertUnprocessable();
    }

    public function test_an_account_with_orders_cannot_be_deleted_only_deactivated(): void
    {
        $this->buildMarketplace();
        $this->order(false);
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->admin, 'sanctum');

        $this->deleteJson("/api/users/{$this->customer->id}")->assertUnprocessable();
        $this->assertNotNull(User::find($this->customer->id));

        $free = tap(User::factory()->create(['company_id' => $this->customer->company_id]), fn ($u) => $u->assignRole('customer'));
        $this->deleteJson("/api/users/{$free->id}")->assertOk();
        $this->assertNull(User::find($free->id));
    }

    public function test_the_user_list_hides_guests_and_filters_by_role_status_and_company_and_shows_the_last_sign_in(): void
    {
        $this->buildMarketplace();
        $guest = User::factory()->create(['is_guest' => true, 'company_id' => $this->customer->company_id]);
        $off = tap(User::factory()->create(['is_active' => false, 'company_id' => $this->customer->company_id]), fn ($u) => $u->assignRole('support-agent'));
        AuditLog::create(['user_id' => $this->customer->id, 'action' => 'auth.login']);
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->admin, 'sanctum');

        $ids = fn (string $q = '') => collect($this->getJson('/api/users'.$q)->assertOk()->json('data'))->pluck('id')->all();
        $this->assertNotContains($guest->id, $ids());
        $this->assertContains($guest->id, $ids('?guests=1'));
        $this->assertSame([$off->id], $ids('?status=inactive'));
        $this->assertSame([$off->id], $ids('?role=support-agent'));
        $this->assertContains($this->customer->id, $ids('?company_id='.$this->customer->company_id));
        $this->assertNotContains($this->admin->id, $ids('?company_id='.$this->customer->company_id));

        $row = collect($this->getJson('/api/users?search='.urlencode($this->customer->email))->json('data'))->firstWhere('id', $this->customer->id);
        $this->assertNotNull($row['last_login_at']);
        $this->assertFalse($row['is_guest']);
    }

    public function test_the_overview_never_adds_money_across_currencies_and_flags_what_needs_doing(): void
    {
        $this->buildMarketplace();
        $this->order(true);
        DB::table('failed_jobs')->insert(['uuid' => 'abc-1', 'connection' => 'database', 'queue' => 'default', 'payload' => json_encode(['displayName' => 'App\\Jobs\\Demo']), 'exception' => "Boom\nstack", 'failed_at' => now()]);
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->admin, 'sanctum');

        $overview = $this->getJson('/api/admin/overview')->assertOk()->json();
        $revenue = collect($overview['numbers'])->firstWhere('key', 'revenue');
        $this->assertIsArray($revenue['by_currency']);
        $this->assertEqualsWithDelta($revenue['value'], array_sum($revenue['by_currency']), 0.01);
        $this->assertContains('failed_jobs', collect($overview['attention'])->pluck('key')->all());
    }

    public function test_the_system_page_reports_health_and_lets_an_administrator_retry_or_remove_a_failed_job(): void
    {
        $this->buildMarketplace();
        DB::table('failed_jobs')->insert(['uuid' => 'abc-2', 'connection' => 'database', 'queue' => 'default', 'payload' => json_encode(['displayName' => 'App\\Jobs\\Demo']), 'exception' => "Boom\nstack", 'failed_at' => now()]);

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->manager, 'sanctum');
        $this->getJson('/api/admin/system')->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->admin, 'sanctum');
        $system = $this->getJson('/api/admin/system')->assertOk()->json();
        $this->assertEqualsCanonicalizing(['scheduler', 'queue', 'database', 'cache', 'disk'], collect($system['checks'])->pluck('key')->all());
        $this->assertSame('App\\Jobs\\Demo', $system['failed_jobs'][0]['job']);
        $this->assertSame('Boom', $system['failed_jobs'][0]['error']);

        $this->deleteJson('/api/admin/system/failed-jobs/abc-2')->assertNoContent();
        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->deleteJson('/api/admin/system/failed-jobs/nope')->assertNotFound();
    }

    public function test_exports_are_csv_for_administrators_only_safe_in_a_spreadsheet_and_audited(): void
    {
        $this->buildMarketplace();
        $this->customer->update(['name' => '=HYPERLINK("http://evil.example")']);
        $this->order(true);

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->manager, 'sanctum');
        $this->getJson('/api/admin/export/users')->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->admin, 'sanctum');
        $users = $this->get('/api/admin/export/users')->assertOk();
        $this->assertStringContainsString('text/csv', $users->headers->get('Content-Type'));
        $csv = $users->streamedContent();
        $this->assertStringContainsString('id,name,email,role,company,active,created_at,last_login_at', $csv);
        $this->assertStringContainsString("\"'=HYPERLINK(", $csv, 'a formula in a name is written as text');
        $this->assertStringNotContainsString(',=HYPERLINK', $csv);

        $orders = $this->get('/api/admin/export/orders')->assertOk()->streamedContent();
        $this->assertStringContainsString('total_amount,currency', $orders);
        $this->assertStringContainsString(',paid,', $orders);
        $this->get('/api/admin/export/payments')->assertOk();
        $this->get('/api/admin/export/payouts')->assertOk();
        $this->get('/api/admin/export/secrets')->assertNotFound();
        $this->assertSame(4, AuditLog::where('action', 'admin.exported')->count());
    }

    public function test_the_search_box_finds_orders_people_companies_and_products_for_administrators_only(): void
    {
        $this->buildMarketplace();
        $id = $this->order(false);

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->manager, 'sanctum');
        $this->getJson('/api/admin/search?q=abc')->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->admin, 'sanctum');
        $this->assertSame([], $this->getJson('/api/admin/search?q=a')->json('groups'), 'one letter is too short to search');

        $byOrder = collect($this->getJson("/api/admin/search?q=%23{$id}")->json('groups'))->keyBy('key');
        $this->assertSame("Order #{$id}", $byOrder['orders']['items'][0]['title']);

        $byPerson = collect($this->getJson('/api/admin/search?q='.urlencode($this->customer->email))->json('groups'))->keyBy('key');
        $this->assertSame($this->customer->email, $byPerson['users']['items'][0]['detail']);
        $this->assertStringContainsString('/dashboard/users?search=', $byPerson['users']['items'][0]['path']);

        $byProduct = collect($this->getJson('/api/admin/search?q='.urlencode(substr($this->product->name, 0, 4)))->json('groups'))->keyBy('key');
        $this->assertSame($this->product->id, $byProduct['products']['items'][0]['id']);
    }

    public function test_the_business_numbers_come_with_the_period_before_and_a_daily_line_that_adds_up(): void
    {
        $this->buildMarketplace();
        $this->order(true);
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->admin, 'sanctum');

        $kpi = $this->getJson('/api/admin/kpis?from='.now()->subDays(6)->toDateString().'&to='.now()->toDateString())->assertOk()->json();
        $this->assertCount(7, $kpi['series']['days']);
        foreach ($kpi['gmv']['total'] as $currency => $total) {
            $this->assertEqualsWithDelta($total, array_sum($kpi['series']['by_currency'][$currency]), 0.01, 'the line adds up to the headline');
        }
        $this->assertSame(now()->subDays(13)->toDateString(), $kpi['previous']['period']['from']);
        $this->assertSame(now()->subDays(7)->toDateString(), $kpi['previous']['period']['to']);
        $this->assertArrayHasKey('gmv', $kpi['previous']);
    }
}
