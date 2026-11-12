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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
        Storage::fake('local');
        app(Settings::class)->set('security.signups_per_ip_per_hour', 100); // the per-IP limit has its own test below
    }

    /** A real file on disk, so its type is judged by its content exactly as in production (a fake upload just echoes its name). */
    private function realFile(string $content, string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'doc');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, null, null, true);
    }

    private function pdf(string $name = 'doc.pdf'): UploadedFile
    {
        return $this->realFile("%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF", $name);
    }

    /** @return array<string, UploadedFile> */
    private function docs(array $kinds = ['id', 'commercial_registration']): array
    {
        return collect($kinds)->mapWithKeys(fn ($k) => [$k => $this->pdf("$k.pdf")])->all();
    }

    private function apply(array $over = [], bool $human = true, ?array $documents = null)
    {
        $token = $human ? $this->getJson('/api/signup-challenge')->json('token') : null;
        $this->travel(5)->seconds();

        return $this->withHeaders(['Accept' => 'application/json'])->post('/api/partner-applications', $over + [
            'type' => 'partner', 'store' => 'main-store', 'business_name' => 'Fresh Film Co', 'contact_name' => 'Sami', 'email' => 'sami@filmco.example.org',
            'phone' => '0501112233', 'city' => 'Riyadh', 'country' => 'sa', 'description' => 'We install paint protection.', 'registration_number' => '1010123456',
            'latitude' => 24.71, 'longitude' => 46.68, 'password' => 'Str0ng-Passw0rd!x', 'password_confirmation' => 'Str0ng-Passw0rd!x',
            'documents' => $documents ?? $this->docs(),
        ] + ($token ? ['signup_token' => $token] : []));
    }

    /** A seller applies to a marketplace (the tenant behind the store). */
    private function applyAsSeller(array $over = [], ?array $documents = null)
    {
        return $this->apply($over + [
            'type' => 'seller', 'store' => null, 'marketplace' => $this->store->tenant->uuid, 'business_name' => 'Gulf Parts Brand', 'email' => 'sales@gulfparts.example.org',
            'tax_number' => '300000000000003',
        ], documents: $documents ?? $this->docs(['id', 'commercial_registration', 'tax_certificate']));
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

        $this->actingAs($this->owner, 'sanctum')->postJson("/api/partner-applications/{$id}/approve", ['documents_verified' => true])->assertOk()->assertJsonPath('status', 'approved');

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
        $this->postJson("/api/partner-applications/{$id}/approve", ['documents_verified' => true])->assertNotFound();

        foreach ([$this->customer, $this->manager, $this->tech] as $nobody) {
            $this->actingAs($nobody, 'sanctum')->getJson('/api/partner-applications')->assertForbidden();
            $this->postJson("/api/partner-applications/{$id}/approve", ['documents_verified' => true])->assertForbidden();
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
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/partner-applications/{$id}/approve", ['documents_verified' => true])->assertOk();
        $this->postJson("/api/partner-applications/{$id}/approve", ['documents_verified' => true])->assertUnprocessable();
        $this->postJson("/api/partner-applications/{$id}/reject")->assertUnprocessable();

        // A different application whose mailbox gets an account before approval is refused.
        $this->apply(['email' => 'lina@other.example.org', 'business_name' => 'Lina Co'])->assertCreated();
        $second = $this->appId();
        User::factory()->create(['email' => 'lina@other.example.org']);
        $this->postJson("/api/partner-applications/{$second}/approve", ['documents_verified' => true])->assertUnprocessable();
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

    // ------------------------------------------------------------------ documents

    public function test_documents_are_required_checked_by_content_and_stored_privately(): void
    {
        // Missing required documents.
        $this->apply([], documents: ['id' => $this->pdf()])->assertUnprocessable()->assertJsonValidationErrors('documents.commercial_registration');
        $this->apply([], documents: [])->assertUnprocessable();

        // A script renamed to .pdf is refused: the file is judged by what it is.
        $fake = $this->realFile('<?php system($_GET["c"]); ?>', 'id.pdf');
        $this->apply([], documents: ['id' => $fake, 'commercial_registration' => $this->pdf()])->assertUnprocessable();
        $exe = $this->realFile("MZ\x90\x00\x03\x00", 'id.exe');
        $this->apply([], documents: ['id' => $exe, 'commercial_registration' => $this->pdf()])->assertUnprocessable();

        // Too large.
        config(['platform.document_max_kb' => 1]);
        $big = $this->realFile("%PDF-1.4\n".str_repeat('A', 4096)."\n%%EOF", 'id.pdf');
        $this->apply([], documents: ['id' => $big, 'commercial_registration' => $this->pdf()])->assertUnprocessable();
        config(['platform.document_max_kb' => 5120]);
        $this->assertSame(0, PartnerApplication::count());

        // A good application: files are kept on the private disk under random names, never in a public path.
        $this->apply([], documents: $this->docs(['id', 'commercial_registration', 'license']))->assertCreated();
        $app = PartnerApplication::first();
        $this->assertSame(['commercial_registration', 'id', 'license'], $app->documents->pluck('kind')->sort()->values()->all());
        foreach ($app->documents as $doc) {
            Storage::disk('local')->assertExists($doc->makeVisible('path')->path);
            $this->assertStringStartsWith("applications/{$app->id}/", $doc->path);
            $this->assertStringNotContainsString('id.pdf', $doc->path);
            $this->assertSame('application/pdf', $doc->mime);
        }
        $this->assertSame([], array_values(array_diff(Storage::disk('public')->allFiles(), ['.gitignore'])));
    }

    public function test_reviewers_open_documents_and_every_view_is_audited(): void
    {
        $this->apply()->assertCreated();
        $app = PartnerApplication::first();
        $doc = $app->documents->first();

        $this->actingAs($this->owner, 'sanctum')->get("/api/partner-applications/{$app->id}/documents/{$doc->id}")
            ->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame(1, \App\Models\AuditLog::where('action', 'partner_application.document_viewed')->count());

        $this->actingAs($this->admin, 'sanctum')->get("/api/partner-applications/{$app->id}/documents/{$doc->id}")->assertOk();

        foreach ([$this->customer, $this->manager] as $nobody) {
            $this->actingAs($nobody, 'sanctum')->get("/api/partner-applications/{$app->id}/documents/{$doc->id}")->assertForbidden();
        }
        $this->app['auth']->forgetGuards();
        $this->getJson("/api/partner-applications/{$app->id}/documents/{$doc->id}")->assertUnauthorized();

        // The list shows file names and sizes, never storage paths.
        $list = $this->actingAs($this->admin, 'sanctum')->getJson('/api/partner-applications')->assertOk();
        $this->assertStringNotContainsString('applications/', $list->getContent());
        $this->assertSame([], $list->json('data.0.missing_documents'));
    }

    public function test_approval_needs_the_verified_confirmation(): void
    {
        $this->apply()->assertCreated();
        $id = $this->appId();
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/partner-applications/{$id}/approve")->assertUnprocessable()->assertJsonValidationErrors('documents_verified');
        $this->postJson("/api/partner-applications/{$id}/approve", ['documents_verified' => false])->assertUnprocessable();
        $this->assertSame('pending', PartnerApplication::find($id)->status);
    }

    // ------------------------------------------------------------------ sellers

    public function test_a_seller_applies_to_a_marketplace_with_the_full_document_set(): void
    {
        $this->store->tenant->update(['market' => 'SA']); // so tax numbers are checked against Saudi format
        $this->applyAsSeller([], documents: $this->docs())->assertUnprocessable()->assertJsonValidationErrors('documents.tax_certificate'); // sellers also need a tax certificate
        $this->applyAsSeller(['tax_number' => '12345'])->assertUnprocessable()->assertJsonValidationErrors('tax_number');
        $this->applyAsSeller(['tax_number' => ''])->assertUnprocessable()->assertJsonValidationErrors('tax_number');
        $this->applyAsSeller(['marketplace' => 'no-such-marketplace'])->assertUnprocessable()->assertJsonValidationErrors('marketplace');

        $this->applyAsSeller()->assertCreated();
        $app = PartnerApplication::first();
        $this->assertSame('seller', $app->type);
        $this->assertNull($app->company_id);
        $this->assertSame(3, $app->documents()->count());
        $this->assertSame(0, User::where('email', 'sales@gulfparts.example.org')->count());
        $this->assertSame(0, Company::where('name', 'Gulf Parts Brand')->count());

        // Only platform admins are told about sellers.
        $this->assertSame(1, $this->admin->notifications()->where('data->kind', 'partner_application')->count());
        $this->assertSame(0, $this->owner->notifications()->where('data->kind', 'partner_application')->count());
    }

    public function test_only_platform_admins_decide_on_sellers_and_approval_creates_a_seller(): void
    {
        $this->applyAsSeller()->assertCreated();
        $id = $this->appId();

        // The store's owner cannot see, open documents of, or decide on a seller.
        $this->actingAs($this->owner, 'sanctum')->getJson('/api/partner-applications?status=all')->assertOk()->assertJsonCount(0, 'data');
        $docId = PartnerApplication::find($id)->documents()->value('id');
        $this->get("/api/partner-applications/{$id}/documents/{$docId}")->assertNotFound();
        $this->postJson("/api/partner-applications/{$id}/approve", ['documents_verified' => true])->assertNotFound();

        $this->actingAs($this->admin, 'sanctum')->getJson('/api/partner-applications?type=seller')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.marketplace', 'T');
        $this->postJson("/api/partner-applications/{$id}/approve", ['documents_verified' => true])->assertOk()->assertJsonPath('status', 'approved');

        $user = User::where('email', 'sales@gulfparts.example.org')->first();
        $this->assertTrue($user->hasRole('seller'));
        $this->assertFalse($user->hasAnyRole(['partner-manager', 'company-owner', 'customer']));
        $company = Company::withoutGlobalScopes()->find($user->company_id);
        $this->assertSame('supplier', $company->type);
        $this->assertSame('active', $company->status);
        $this->assertSame($this->store->tenant_id, $company->tenant_id);
        $this->assertSame(0, Partner::where('name', 'Gulf Parts Brand')->count()); // a seller is not a service partner

        // Signs in at once; sees nothing of the admin side.
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/login', ['email' => 'sales@gulfparts.example.org', 'password' => 'Str0ng-Passw0rd!x'])->assertOk();
        $this->actingAs($user->fresh(), 'sanctum')->getJson('/api/admin/settings')->assertForbidden();
        $this->getJson('/api/partner-applications')->assertForbidden();
        $this->getJson('/api/commissions')->assertForbidden();
    }

    public function test_marketplaces_are_listed_publicly_without_private_data(): void
    {
        $res = $this->getJson('/api/public/marketplaces')->assertOk();
        $this->assertSame([$this->store->tenant->uuid], array_column($res->json('marketplaces'), 'key'));
        $this->assertSame('T', $res->json('marketplaces.0.name'));
    }

    public function test_documents_of_old_rejected_applications_are_pruned_and_others_are_kept(): void
    {
        $this->apply()->assertCreated();
        $rejected = PartnerApplication::first();
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/partner-applications/{$rejected->id}/reject", ['reason' => 'No'])->assertOk();
        $this->app['auth']->forgetGuards();

        $this->apply(['email' => 'keep@filmco.example.org', 'business_name' => 'Keep Co'])->assertCreated();
        $pending = PartnerApplication::latest('id')->first();
        $path = $rejected->documents->first()->makeVisible('path')->path;

        // Recently rejected: kept. Old: removed. Pending ones are never touched.
        $this->artisan('applications:prune-documents', ['--days' => 90])->assertSuccessful();
        Storage::disk('local')->assertExists($path);

        PartnerApplication::whereKey($rejected->id)->update(['reviewed_at' => now()->subDays(120)]);
        $this->artisan('applications:prune-documents', ['--days' => 90])->assertSuccessful();
        Storage::disk('local')->assertMissing($path);
        $this->assertSame(0, $rejected->documents()->count());
        $this->assertSame(2, $pending->documents()->count());
    }

    public function test_a_business_has_one_seller_account_per_market_and_may_join_another_market_separately(): void
    {
        $this->applyAsSeller()->assertCreated();
        // The same business (same registration number) cannot apply twice to the same market, even with another e-mail...
        $this->applyAsSeller(['email' => 'second@gulfparts.example.org'])->assertStatus(422)->assertJsonValidationErrors('registration_number');

        // ...but another country's market is a separate marketplace, with its own account and terms.
        $other = app(\App\Services\MarketLauncher::class)->open('AE', 'ArmorHub UAE', [\App\Models\Industry::value('id')], null);
        $this->applyAsSeller(['marketplace' => $other->uuid, 'email' => 'uae@gulfparts.example.org', 'phone' => '0501234567', 'country' => 'ae', 'tax_number' => '100000000000009'])->assertCreated();
        $this->assertSame([$this->store->tenant_id, $other->id], PartnerApplication::orderBy('id')->pluck('tenant_id')->all());

        $list = $this->getJson('/api/public/marketplaces')->assertOk()->json('marketplaces');
        $this->assertContains('ArmorHub UAE', array_column($list, 'name'));
        $this->assertSame('AED', collect($list)->firstWhere('name', 'ArmorHub UAE')['currency']);
    }
}
