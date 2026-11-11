<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Industry;
use App\Models\Partner;
use App\Models\PartnerApplication;
use App\Models\PartnerQuote;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class PartnerApplicationTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private Company $store;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();

        // The marketplace's store, opened to the public, with an owner.
        $this->store = Company::withoutGlobalScopes()->where('name', 'Store')->first();
        $this->store->update(['slug' => 'main-store', 'settings' => ['storefront' => ['enabled' => true]]]);
        $this->owner = User::factory()->create(['company_id' => $this->store->id]);
        $this->owner->assignRole('company-owner');
        app(Settings::class)->set('security.signups_per_ip_per_hour', 100); // the per-IP limit has its own test below
    }

    private function apply(array $over = [], bool $human = true)
    {
        $token = $human ? $this->getJson('/api/signup-challenge')->json('token') : null;
        $this->travel(5)->seconds();

        return $this->postJson('/api/partner-applications', $over + [
            'store' => 'main-store', 'business_name' => 'Fresh Film Co', 'contact_name' => 'Sami', 'email' => 'sami@filmco.example.org',
            'phone' => '0501112233', 'city' => 'Riyadh', 'country' => 'sa', 'description' => 'We install paint protection.',
            'latitude' => 24.71, 'longitude' => 46.68, 'password' => 'Str0ng-Passw0rd!x', 'password_confirmation' => 'Str0ng-Passw0rd!x',
        ] + ($token ? ['signup_token' => $token] : []));
    }

    private function appId(): int
    {
        return PartnerApplication::latest('id')->value('id');
    }

    public function test_an_application_creates_nothing_but_a_pending_request(): void
    {
        $this->apply()->assertCreated();

        $app = PartnerApplication::first();
        $this->assertSame('pending', $app->status);
        $this->assertSame('SA', $app->country);
        $this->assertNotSame('Str0ng-Passw0rd!x', $app->makeVisible('password')->password); // stored hashed
        $this->assertSame(0, User::where('email', 'sami@filmco.example.org')->count());      // no account yet
        $this->assertSame(0, Partner::where('name', 'Fresh Film Co')->count());                // no partner yet

        // The people who can decide are told; nobody else is.
        $this->assertSame(1, $this->owner->notifications()->where('data->kind', 'partner_application')->count());
        $this->assertSame(1, $this->admin->notifications()->where('data->kind', 'partner_application')->count());
        $this->assertSame(0, $this->customer->notifications()->where('data->kind', 'partner_application')->count());
    }

    public function test_applications_are_validated_and_bots_are_turned_away(): void
    {
        $this->apply(['store' => 'nope'])->assertUnprocessable()->assertJsonValidationErrors('store');
        $this->apply(['password' => 'short', 'password_confirmation' => 'short'])->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->apply(['phone' => '123'])->assertUnprocessable()->assertJsonValidationErrors('phone');
        $this->apply(['business_name' => ''])->assertUnprocessable()->assertJsonValidationErrors('business_name');
        $this->apply(['email' => 'x@mailinator.com'])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->apply([], human: false)->assertUnprocessable();                       // no timing token
        $this->apply(['website' => 'http://spam.example'])->assertUnprocessable();    // honeypot
        $this->assertSame(0, PartnerApplication::count());

        app(Settings::class)->set('platform.partner_signup_open', false);
        $this->apply()->assertForbidden();
    }

    public function test_one_mailbox_can_only_apply_once_and_cannot_use_an_existing_account(): void
    {
        $this->apply()->assertCreated();
        $this->apply(['email' => 'sami+again@filmco.example.org'])->assertUnprocessable()->assertJsonValidationErrors('email'); // plus-alias of the same mailbox
        $this->apply(['email' => $this->customer->email])->assertUnprocessable()->assertJsonValidationErrors('email');

        // After a rejection the business may apply again.
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/partner-applications/{$this->appId()}/reject", ['reason' => 'Incomplete'])->assertOk();
        $this->app['auth']->forgetGuards();
        $this->apply()->assertCreated();
    }

    public function test_approval_grants_the_partner_role_immediately_and_the_partner_can_work(): void
    {
        $this->apply()->assertCreated();
        $id = $this->appId();

        // Before approval: no sign in, but a clear message (only to someone who knows the password).
        $this->postJson('/api/login', ['email' => 'sami@filmco.example.org', 'password' => 'Str0ng-Passw0rd!x'])
            ->assertUnprocessable()->assertJsonPath('errors.email.0', fn ($m) => str_contains($m, 'still being reviewed'));
        $this->postJson('/api/login', ['email' => 'sami@filmco.example.org', 'password' => 'wrong-password-1'])
            ->assertUnprocessable()->assertJsonPath('errors.email.0', 'Invalid credentials.');

        $this->actingAs($this->owner, 'sanctum')->postJson("/api/partner-applications/{$id}/approve")->assertOk()->assertJsonPath('status', 'approved');

        $user = User::where('email', 'sami@filmco.example.org')->first();
        $this->assertTrue($user->hasRole('partner-manager'));
        $this->assertNotNull($user->partner_id);
        $partner = Partner::withoutGlobalScopes()->find($user->partner_id);
        $this->assertSame('Fresh Film Co', $partner->name);
        $this->assertSame($this->store->tenant_id, Company::withoutGlobalScopes()->find($user->company_id)->tenant_id); // same marketplace
        $this->assertTrue((bool) $partner->location_enabled);

        // Signs in with the password chosen at application time, right away.
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/login', ['email' => 'sami@filmco.example.org', 'password' => 'Str0ng-Passw0rd!x'])->assertOk()->assertJsonPath('user.email', 'sami@filmco.example.org');

        // And is matched with customers nearby: a new order asks this partner for a quote.
        $orderId = $this->actingAs($this->customer, 'sanctum')->postJson('/api/orders', [
            'product_id' => $this->product->id, 'target_type' => 'vehicle_part', 'targets' => [$this->hood->id],
        ])->assertCreated()->json('order.id');
        $this->assertTrue(PartnerQuote::withoutGlobalScopes()->where('order_id', $orderId)->where('partner_id', $partner->id)->exists());

        $this->actingAs($user->fresh(), 'sanctum')->getJson('/api/partner/orders')->assertOk()->assertJsonCount(1);
        // The new partner cannot do admin things.
        $this->getJson('/api/admin/settings')->assertForbidden();
        $this->getJson('/api/partner-applications')->assertForbidden();
    }

    public function test_rejection_keeps_the_applicant_out_and_says_so(): void
    {
        $this->apply()->assertCreated();
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/partner-applications/{$this->appId()}/reject", ['reason' => 'We could not verify the business'])
            ->assertOk()->assertJsonPath('status', 'rejected')->assertJsonPath('rejection_reason', 'We could not verify the business');

        $this->assertSame(0, User::where('email', 'sami@filmco.example.org')->count());
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/login', ['email' => 'sami@filmco.example.org', 'password' => 'Str0ng-Passw0rd!x'])
            ->assertUnprocessable()->assertJsonPath('errors.email.0', 'Your partner application was not approved.');
    }

    public function test_only_the_right_reviewers_can_see_and_decide(): void
    {
        $this->apply()->assertCreated();
        $id = $this->appId();

        // Another store's owner never sees, and cannot decide, this application.
        $tenant2 = Tenant::create(['name' => 'T2', 'industry_id' => Industry::first()->id]);
        $other = Company::create(['tenant_id' => $tenant2->id, 'name' => 'Other Store']);
        $stranger = User::factory()->create(['company_id' => $other->id]);
        $stranger->assignRole('company-owner');
        $this->actingAs($stranger, 'sanctum')->getJson('/api/partner-applications')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson("/api/partner-applications/{$id}/approve")->assertNotFound();

        foreach ([$this->customer, $this->manager, $this->tech] as $nobody) {
            $this->actingAs($nobody, 'sanctum')->getJson('/api/partner-applications')->assertForbidden();
            $this->postJson("/api/partner-applications/{$id}/approve")->assertForbidden();
        }
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/partner-applications')->assertUnauthorized();

        // The store's own owner and any admin see it, and the password hash never leaves the server.
        $res = $this->actingAs($this->owner, 'sanctum')->getJson('/api/partner-applications')->assertOk()->assertJsonCount(1, 'data');
        $this->assertStringNotContainsString('password', $res->getContent());
        $this->actingAs($this->admin, 'sanctum')->getJson('/api/partner-applications?status=all')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_a_decision_is_final_and_approval_refuses_a_mailbox_that_registered_meanwhile(): void
    {
        $this->apply()->assertCreated();
        $id = $this->appId();
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/partner-applications/{$id}/approve")->assertOk();
        $this->postJson("/api/partner-applications/{$id}/approve")->assertUnprocessable();
        $this->postJson("/api/partner-applications/{$id}/reject")->assertUnprocessable();

        // A different application whose mailbox gets an account before approval is refused.
        $this->apply(['email' => 'lina@other.example.org', 'business_name' => 'Lina Co'])->assertCreated();
        $second = $this->appId();
        User::factory()->create(['email' => 'lina@other.example.org']);
        $this->postJson("/api/partner-applications/{$second}/approve")->assertUnprocessable();
        $this->assertSame(0, Partner::where('name', 'Lina Co')->count());
        $this->assertSame('pending', PartnerApplication::find($second)->status);
    }

    public function test_customers_still_sign_up_normally(): void
    {
        $token = $this->getJson('/api/signup-challenge')->json('token');
        $this->travel(5)->seconds();
        $this->postJson('/api/register', ['name' => 'Buyer', 'email' => 'buyer@example.org', 'password' => 'Str0ng-Passw0rd!x', 'password_confirmation' => 'Str0ng-Passw0rd!x',
            'store' => 'main-store', 'signup_token' => $token])->assertCreated();
        $this->assertTrue(User::where('email', 'buyer@example.org')->first()->hasRole('customer'));
        $this->assertSame(0, PartnerApplication::count());
    }

    public function test_applications_are_rate_limited_per_address(): void
    {
        app(Settings::class)->set('security.signups_per_ip_per_hour', 2);
        $this->apply(['email' => 'a1@x1.example.org'])->assertCreated();
        $this->apply(['email' => 'a2@x2.example.org'])->assertCreated();
        $this->apply(['email' => 'a3@x3.example.org'])->assertStatus(429);
    }
}
