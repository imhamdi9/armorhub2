<?php

namespace Tests\Feature;

use App\Models\AdSpend;
use App\Models\Company;
use App\Models\Tenant;
use App\Services\Marketing\AdSpendSync;
use App\Support\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** Ad accounts: the platform has one, a market may have its own; spend is read (never written to the platforms) and set beside what each campaign brought. */
class AdSpendTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
        $store = Company::withoutGlobalScopes()->where('name', 'Store')->first();
        $store->tenant->update(['market' => 'SA']);
        $this->tenant = $store->tenant->fresh();
        config(['marketing.endpoints' => ['meta' => 'https://graph.facebook.com/v21.0', 'tiktok' => 'https://x.test/t', 'google' => 'https://x.test/g', 'tiktok_report' => 'https://business-api.tiktok.com/open_api/v1.3/report/integrated/get/']]);
    }

    private function set(?int $market, array $values): void
    {
        $s = app(Settings::class);
        $s->inMarket($market, function () use ($s, $values) {
            foreach ($values as $k => $v) {
                $s->set("marketing.$k", $v);
            }
        });
        $s->flush();
    }

    private function fakeMeta(string $campaign = 'Eid Sale', string $spend = '250.50'): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['data' => [['date_start' => now()->toDateString(), 'campaign_id' => '9001', 'campaign_name' => $campaign, 'spend' => $spend, 'impressions' => '12000', 'clicks' => '340', 'account_currency' => 'SAR']]]),
            'business-api.tiktok.com/*' => Http::response(['code' => 0, 'data' => ['list' => [['dimensions' => ['campaign_id' => '77', 'stat_time_day' => now()->toDateString().' 00:00:00'], 'metrics' => ['campaign_name' => 'Ramadan Launch', 'spend' => '90.00', 'impressions' => '5000', 'clicks' => '100', 'currency' => 'SAR']]], 'page_info' => ['total_page' => 1]]]),
        ]);
    }

    public function test_the_platform_account_is_read_once_even_when_markets_inherit_it(): void
    {
        $this->set(null, ['meta_ad_account_id' => 'act_123456', 'meta_ads_token' => 'tok', 'tiktok_advertiser_id' => '555666', 'tiktok_ads_token' => 'tt']);
        $this->fakeMeta();

        $results = app(AdSpendSync::class)->run(7);

        $this->assertSame(['meta', 'tiktok'], collect($results)->pluck('platform')->all());          // the market inherits: no second read
        $this->assertSame(['ok', 'ok'], collect($results)->pluck('status')->all());
        $row = AdSpend::where('platform', 'meta')->firstOrFail();
        $this->assertNull($row->owner_tenant_id);
        $this->assertSame('eid-sale', $row->campaign_key);
        $this->assertEquals(250.5, $row->spend);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'act_123456/insights') && $r->method() === 'GET');   // reading only
        Http::assertNotSent(fn ($r) => $r->method() !== 'GET');
    }

    public function test_a_market_with_its_own_account_is_read_for_that_market(): void
    {
        $this->set(null, ['meta_ad_account_id' => '111111', 'meta_ads_token' => 'tok']);
        $this->set($this->tenant->id, ['meta_ad_account_id' => '222222', 'meta_ads_token' => 'tok2']);
        $this->fakeMeta();

        $results = collect(app(AdSpendSync::class)->run(7));

        $this->assertCount(2, $results->where('platform', 'meta'));
        $this->assertSame($this->tenant->id, $results->firstWhere('account', '222222')['owner']);
        $this->assertNull($results->firstWhere('account', '111111')['owner']);
    }

    public function test_a_failing_platform_is_reported_and_does_not_stop_the_others(): void
    {
        $this->set(null, ['meta_ad_account_id' => '111111', 'meta_ads_token' => 'bad', 'tiktok_advertiser_id' => '555666', 'tiktok_ads_token' => 'tt']);
        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid OAuth access token']], 400),
            'business-api.tiktok.com/*' => Http::response(['code' => 0, 'data' => ['list' => [], 'page_info' => ['total_page' => 1]]]),
        ]);

        $results = collect(app(AdSpendSync::class)->run(7));

        $this->assertSame('failed', $results->firstWhere('platform', 'meta')['status']);
        $this->assertStringContainsString('Invalid OAuth', $results->firstWhere('platform', 'meta')['note']);
        $this->assertSame('ok', $results->firstWhere('platform', 'tiktok')['status']);
    }

    public function test_the_admins_are_told_when_a_day_passes_the_limit_or_reading_fails(): void
    {
        $admin = tap(\App\Models\User::factory()->create(), fn ($u) => $u->assignRole('platform-admin'));
        $this->set(null, ['meta_ad_account_id' => '111111', 'meta_ads_token' => 'tok', 'daily_spend_alert' => 100]);
        $this->fakeMeta('Eid Sale', '250.50');
        app(AdSpendSync::class)->run(7);
        $this->assertTrue($admin->notifications()->get()->pluck('data.title')->contains('Ad spend passed the daily limit'));

        \Illuminate\Support\Facades\Cache::flush();
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(['*' => Http::response(['error' => ['message' => 'expired']], 400)]);
        app(AdSpendSync::class)->run(7);
        $this->assertTrue($admin->notifications()->get()->pluck('data.title')->contains('Reading the ad spend failed'));
    }
}
