<?php

namespace Tests\Feature;

use App\Models\AdConversionLog;
use App\Models\Company;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** Ad campaigns: pixel ids per market, the campaign kept on the order (only with consent), purchases reported from the server, and the product feed. */
class AdMarketingTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private Tenant $tenant;
    private int $productId;
    private array $address = ['name' => 'Layla', 'phone' => '+966 50 123 4567', 'line1' => '12 King Road', 'city' => 'Riyadh'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
        $store = Company::withoutGlobalScopes()->where('name', 'Store')->first();
        $store->update(['slug' => 'main-store', 'settings' => ['storefront' => ['enabled' => true]]]);
        $store->tenant->update(['market' => 'SA']);
        $this->tenant = $store->tenant->fresh();
        $seller = tap(User::factory()->create(['company_id' => Company::create(['tenant_id' => $store->tenant_id, 'name' => 'Nile Goods', 'type' => 'supplier', 'status' => 'active'])->id]), fn ($u) => $u->assignRole('seller'));
        $this->actingAs($seller, 'sanctum');
        $cat = $this->getJson('/api/products/categories')->json('0.key');
        $this->productId = $this->postJson('/api/products', ['name' => 'Olive Soap', 'base_price' => 100, 'category' => $cat, 'images' => ['https://img.example.org/soap.jpg']])->assertCreated()->json('id');
        $this->app['auth']->forgetGuards();
    }

    private function configure(): void
    {
        config(['marketing.endpoints' => ['meta' => 'https://graph.facebook.com/v21.0', 'tiktok' => 'https://business-api.tiktok.com/open_api/v1.3/event/track/', 'google' => 'https://www.google-analytics.com/mp/collect']]);
        $s = app(Settings::class);
        $s->inMarket($this->tenant->id, function () use ($s) {
            foreach ([
                'marketing.meta_pixel_id' => '123456789', 'marketing.meta_access_token' => 'meta-secret', 'marketing.meta_test_code' => 'TEST1',
                'marketing.tiktok_pixel_id' => 'TT12345', 'marketing.tiktok_access_token' => 'tt-secret',
                'marketing.ga4_id' => 'G-ABCD1234', 'marketing.ga4_api_secret' => 'ga-secret',
            ] as $k => $v) {
                $s->set($k, $v);
            }
        });
        $s->flush();
    }

    /** A guest shops and pays; returns the order. */
    private function buy(?array $campaign): Order
    {
        $this->app['auth']->forgetGuards();
        $this->withoutHeader('Authorization');
        $token = $this->postJson('/api/guest-session', ['store' => 'main-store'])->assertCreated()->json('token');
        $this->app['auth']->forgetGuards();
        $h = $this->withHeader('Authorization', 'Bearer '.$token);
        $h->postJson('/api/cart/items', ['product_id' => $this->productId, 'quantity' => 1])->assertCreated();
        $this->putJson('/api/guest-session/contact', ['email' => 'Layla@Shoppers.example.org', 'name' => 'Layla'])->assertOk();
        if ($campaign) {
            $this->withHeader('X-Campaign', json_encode($campaign));
        }
        $group = $this->postJson('/api/checkout', ['shipping_address' => $this->address])->assertCreated()->json();
        $this->withoutHeader('X-Campaign');
        $this->postJson("/api/order-groups/{$group['id']}/payments", ['payment_method' => 'card'])->assertOk();

        return Order::withoutGlobalScopes()->where('group_id', $group['id'])->firstOrFail();
    }

    public function test_the_browser_gets_only_the_public_ids_of_the_market(): void
    {
        $this->configure();
        $json = $this->getJson('/api/public/marketing-config?market=SA')->assertOk()->json();
        $this->assertSame('123456789', $json['meta_pixel_id']);
        $this->assertSame('G-ABCD1234', $json['ga4_id']);
        $this->assertStringNotContainsString('secret', json_encode($json));
        $this->assertNull($this->getJson('/api/public/marketing-config?market=EG')->json('meta_pixel_id'));       // another market has its own (empty) ids
    }

    public function test_a_paid_order_with_consent_is_reported_to_every_platform_once_with_hashed_details(): void
    {
        $this->configure();
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $order = $this->buy(['utm_source' => 'facebook', 'utm_campaign' => 'eid-sale', 'fbclid' => 'abc', 'fbp' => 'fb.1.1', 'ttclid' => 'tt1', 'gclid' => 'g1', 'source_url' => 'https://shop.example.org/s/main-store', 'junk' => 'x']);

        $this->assertSame('eid-sale', $order->attribution['utm_campaign']);
        $this->assertArrayNotHasKey('junk', $order->attribution);
        $this->assertSame(3, AdConversionLog::where(['event_id' => 'order-'.$order->id, 'status' => 'sent'])->count());

        $hash = hash('sha256', 'layla@shoppers.example.org');
        Http::assertSent(fn ($r) => str_contains($r->url(), '/123456789/events') && $r['data'][0]['event_id'] === 'order-'.$order->id
            && $r['data'][0]['user_data']['em'][0] === $hash && $r['test_event_code'] === 'TEST1' && $r['data'][0]['custom_data']['currency'] === $order->currency);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'business-api.tiktok.com') && $r['data'][0]['event'] === 'CompletePayment' && $r['data'][0]['user']['email'] === $hash);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'measurement_id=G-ABCD1234') && $r['events'][0]['name'] === 'purchase');
        Http::assertNotSent(fn ($r) => str_contains(json_encode($r->data()), 'layla@shoppers'));                   // the plain address never leaves
    }

    public function test_without_consent_nothing_is_stored_or_sent(): void
    {
        $this->configure();
        Http::fake();

        $order = $this->buy(null);

        $this->assertNull($order->attribution);
        $this->assertNull($order->ad_context);
        Http::assertNothingSent();
        $this->assertSame('skipped', AdConversionLog::where('event_id', 'order-'.$order->id)->value('status'));
    }

    public function test_a_market_with_no_platform_switched_on_sends_nothing(): void
    {
        Http::fake();
        $order = $this->buy(['utm_source' => 'tiktok']);
        $this->assertSame('tiktok', $order->attribution['utm_source']);
        Http::assertNothingSent();
    }

    public function test_the_admin_sees_what_each_campaign_brought_and_can_run_a_connection_test(): void
    {
        $this->configure();
        Http::fake(['*' => Http::response([], 200)]);
        $this->buy(['utm_source' => 'facebook', 'utm_campaign' => 'eid-sale']);
        $this->buy(null);

        $this->app['auth']->forgetGuards();
        $this->actingAs(tap(User::factory()->create(), fn ($u) => $u->assignRole('platform-admin')), 'sanctum');
        $rows = collect($this->getJson('/api/admin/marketing/attribution?days=30')->assertOk()->json('rows'));
        $this->assertSame(1, $rows->firstWhere('campaign', 'eid-sale')['orders']);
        $this->assertSame(1, $rows->firstWhere('source', 'direct / not tracked')['orders']);

        $results = $this->postJson("/api/admin/marketing/test?market={$this->tenant->id}")->assertOk()->json('results');
        $this->assertCount(3, $results);
        $this->assertSame(['sent'], collect($results)->pluck('status')->unique()->all());
        $this->assertGreaterThan(0, count($this->getJson('/api/admin/marketing/logs')->assertOk()->json('data')));
    }

    public function test_the_feed_lists_only_what_an_ad_can_sell_directly(): void
    {
        $xml = $this->get('/api/public/feeds/main-store/google.xml')->assertOk()->getContent();
        $this->assertStringContainsString('<g:id>'.$this->productId.'</g:id>', $xml);
        $this->assertStringContainsString('Olive Soap', $xml);
        $this->assertStringContainsString('/s/main-store/products/'.$this->productId, $xml);
        $csv = $this->get('/api/public/feeds/main-store/meta.csv')->assertOk()->getContent();
        $this->assertStringContainsString('Olive Soap', $csv);
        $this->assertStringContainsString('<g:identifier_exists>no</g:identifier_exists>', $xml);
        $this->get('/api/public/feeds/main-store/google-ar.xml')->assertOk();                 // the Arabic variant
        $this->get('/api/public/feeds/main-store/tiktok-ar.csv')->assertOk();
        $this->get('/api/public/feeds/nope/google.xml')->assertNotFound();
    }

    public function test_cost_and_return_of_a_campaign_are_worked_out_from_its_spend_and_its_orders(): void
    {
        Http::fake();
        $order = $this->buy(['utm_source' => 'facebook', 'utm_campaign' => 'Eid Sale']);                    // revenue of the campaign
        $revenue = (float) $order->payable_amount;
        \App\Models\AdSpend::create(['platform' => 'meta', 'account_id' => '1', 'day' => now()->toDateString(), 'campaign_id' => 'c1', 'campaign_name' => 'Eid Sale', 'campaign_key' => 'eid-sale',
            'spend' => 100, 'currency' => $order->currency, 'impressions' => 9000, 'clicks' => 300]);
        \App\Models\AdSpend::create(['platform' => 'tiktok', 'account_id' => '2', 'day' => now()->toDateString(), 'campaign_id' => 't1', 'campaign_name' => 'No Orders Yet', 'campaign_key' => 'no-orders-yet',
            'spend' => 40, 'currency' => $order->currency, 'impressions' => 100, 'clicks' => 5]);

        $this->app['auth']->forgetGuards();
        $this->actingAs(tap(User::factory()->create(), fn ($u) => $u->assignRole('platform-admin')), 'sanctum');
        $res = $this->getJson('/api/admin/marketing/attribution?days=30')->assertOk();
        $row = collect($res->json('rows'))->firstWhere('campaign', 'eid-sale');

        $this->assertEquals(100.0, $row['spend']);
        $this->assertEquals(100.0, $row['cost_per_order']);
        $this->assertEquals(round($revenue / 100, 2), $row['roas']);
        $this->assertSame(300, $row['clicks']);
        $idle = collect($res->json('rows'))->firstWhere('campaign', 'no-orders-yet');       // spent, brought nothing yet: still listed
        $this->assertSame('tiktok', $idle['source']);
        $this->assertSame(0, $idle['orders']);
        $this->assertEquals(140.0, $res->json('totals.spend'));
    }

    public function test_a_sale_an_ad_platform_refused_is_sent_again_and_then_given_up_with_the_admins_told(): void
    {
        $this->configure();
        $admin = tap(User::factory()->create(), fn ($u) => $u->assignRole('platform-admin'));
        Http::fake(['*' => Http::sequence()->push([], 500)->push([], 500)->push([], 200)->push([], 200)]);          // meta fails first, tiktok fails first, google ok first
        $order = $this->buy(['utm_source' => 'facebook']);

        $this->assertSame(2, AdConversionLog::where(['event_id' => 'order-'.$order->id, 'status' => 'failed'])->count());

        // five minutes later the command tries the two that failed (now accepted)
        \Illuminate\Support\Carbon::setTestNow(now()->addMinutes(10));
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(['*' => Http::response([], 200)]);
        $this->artisan('marketing:retry-conversions')->assertSuccessful();
        $this->assertSame(3, AdConversionLog::where(['event_id' => 'order-'.$order->id, 'status' => 'sent'])->count());

        // a platform that keeps refusing: four tries, then the admins are told once and it stops
        $other = Order::withoutGlobalScopes()->where('id', '!=', $order->id)->first() ?? $order;
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(['*' => Http::response(['error' => 'bad token'], 400)]);
        for ($i = 0; $i < 6; $i++) {
            \Illuminate\Support\Carbon::setTestNow(now()->addMinutes(10));
            app(\App\Services\Marketing\AdConversions::class)->purchase($other->fresh()->forceFill(['id' => 99001, 'ad_context' => ['ip' => '1.1.1.1', 'ua' => 'x'], 'attribution' => []]));
        }
        $this->assertSame(\App\Services\Marketing\AdConversions::MAX_TRIES, AdConversionLog::where(['event_id' => 'order-99001', 'platform' => 'meta', 'status' => 'failed'])->count());
        $titles = $admin->notifications()->get()->pluck('data.title');
        $this->assertSame(3, $titles->filter(fn ($t) => $t === 'An ad platform is not receiving your sales')->count());   // one per platform that gave up, once a day
        \Illuminate\Support\Carbon::setTestNow();
    }

    public function test_a_refund_reaches_google_analytics_and_takes_the_money_out_of_the_campaign_revenue(): void
    {
        $this->configure();
        Http::fake(['*' => Http::response([], 200)]);
        $order = $this->buy(['utm_source' => 'facebook', 'utm_campaign' => 'eid-sale']);
        $paid = (float) $order->payable_amount;

        $admin = tap(User::factory()->create(), fn ($u) => $u->assignRole('platform-admin'));
        \App\Models\OrderEvent::record($order, 'partially_refunded', $admin, ['amount' => '100', 'reason' => 'late']);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'measurement_id=G-ABCD1234') && $r['events'][0]['name'] === 'refund' && (float) $r['events'][0]['params']['value'] === 100.0);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/events') && isset($r['data'][0]['event_name']) && $r['data'][0]['event_name'] === 'Refund');

        // the order's own record: part of it was refunded, so the campaign brought less
        $order->forceFill(['partial_refund_status' => 'owed', 'partial_refund_amount' => 100])->saveQuietly();
        $this->app['auth']->forgetGuards();
        $this->actingAs($admin, 'sanctum');
        $row = collect($this->getJson('/api/admin/marketing/attribution?days=30')->json('rows'))->firstWhere('campaign', 'eid-sale');
        $this->assertEquals($paid - 100, $row['revenue']);

        $order->forceFill(['refund_status' => 'owed'])->saveQuietly();
        $row = collect($this->getJson('/api/admin/marketing/attribution?days=30')->json('rows'))->firstWhere('campaign', 'eid-sale');
        $this->assertEquals(0, $row['revenue']);
    }

    public function test_an_approved_seller_is_reported_as_a_completed_registration(): void
    {
        $this->configure();
        Http::fake(['*' => Http::response([], 200)]);
        $application = \App\Models\PartnerApplication::create([
            'type' => 'seller', 'tenant_id' => $this->tenant->id, 'business_name' => 'Nile Goods Co', 'contact_name' => 'Sara', 'email' => 'sara@nilegoods.example.org', 'email_canonical' => 'sara@nilegoods.example.org',
            'phone' => '+966501234567', 'city' => 'Riyadh', 'country' => 'SA', 'registration_number' => 'CR123', 'tax_number' => 'TX1', 'status' => 'pending', 'password' => bcrypt('Str0ng-Passw0rd!x'),
        ]);
        $application->forceFill(['attribution' => ['utm_source' => 'tiktok', 'utm_campaign' => 'sellers'], 'ad_context' => ['ip' => '1.2.3.4', 'ua' => 'x']])->save();
        $admin = tap(User::factory()->create(), fn ($u) => $u->assignRole('platform-admin'));

        app(\App\Services\PartnerApplicationService::class)->approve($application, $admin);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/123456789/events') && $r['data'][0]['event_name'] === 'CompleteRegistration' && $r['data'][0]['event_id'] === 'approved-'.$application->id);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'tiktok') && $r['data'][0]['event'] === 'CompleteRegistration');
        Http::assertSent(fn ($r) => str_contains($r->url(), 'measurement_id') && $r['events'][0]['name'] === 'qualify_lead');
    }

    public function test_a_marketing_manager_runs_the_ads_and_nothing_else(): void
    {
        $manager = tap(User::factory()->create(), fn ($u) => $u->assignRole('marketing-manager'));
        $this->app['auth']->forgetGuards();
        $this->actingAs($manager, 'sanctum');

        $this->getJson('/api/admin/marketing/attribution')->assertOk();
        $this->getJson('/api/admin/marketing/markets')->assertOk()->assertJsonFragment(['market' => 'SA']);
        $this->putJson("/api/admin/marketing/settings?market={$this->tenant->id}", ['values' => ['marketing.meta_pixel_id' => '987654321']])->assertOk();
        $this->assertSame('987654321', app(Settings::class)->inMarket($this->tenant->id, fn () => app(Settings::class)->get('marketing.meta_pixel_id')));

        // the marketing settings only: no other setting, no other admin screen
        $this->putJson('/api/admin/marketing/settings', ['values' => ['platform.currency' => 'USD']])->assertUnprocessable();
        $this->deleteJson('/api/admin/marketing/settings/platform.currency')->assertNotFound();
        $this->getJson('/api/admin/settings')->assertForbidden();
        $this->getJson('/api/admin/markets')->assertForbidden();
        $this->getJson('/api/admin/payments/providers')->assertForbidden();

        // an ordinary customer has no way in
        $this->app['auth']->forgetGuards();
        $this->actingAs(tap(User::factory()->create(), fn ($u) => $u->assignRole('customer')), 'sanctum');
        $this->getJson('/api/admin/marketing/attribution')->assertForbidden();
    }
}
