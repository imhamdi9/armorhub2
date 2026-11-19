<?php

namespace Tests\Feature;

use App\Models\Industry;
use App\Models\Tenant;
use App\Models\User;
use App\Services\MarketLauncher;
use App\Services\Payment\PaymentProviders;
use App\Support\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Each market has its own payment, shipping and fee settings; a market manager reaches only their own. */
class MarketSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->artisan('platform:demo-egypt')->assertSuccessful();
    }

    private function as(string $email): static
    {
        return $this->actingAs(User::where('email', $email)->firstOrFail(), 'sanctum');
    }

    private function egypt(): Tenant
    {
        return Tenant::where('market', 'EG')->firstOrFail();
    }

    private function brazil(): Tenant
    {
        return app(MarketLauncher::class)->open('BR', 'ArmorHub Brazil', [Industry::value('id')], null);
    }

    public function test_a_markets_own_values_apply_to_that_market_only(): void
    {
        $settings = app(Settings::class);
        $providers = app(PaymentProviders::class);
        $eg = $this->egypt();
        $br = $this->brazil();

        $settings->inMarket($eg->id, fn () => $settings->set('payments.active_provider', 'stripe'));
        $settings->inMarket($br->id, fn () => $settings->set('installation.fee_percent', 9));

        $this->assertSame('simulated', $providers->activeKey());                                          // platform-wide: untouched
        $this->assertSame('stripe', $settings->inMarket($eg->id, fn () => $providers->activeKey()));       // Egypt chose Stripe
        $this->assertSame('simulated', $settings->inMarket($br->id, fn () => $providers->activeKey()));   // Brazil did not
        $this->assertEquals(9.0, $settings->inMarket($br->id, fn () => (float) $settings->get('installation.fee_percent')));
        $this->assertEquals(5.0, $settings->inMarket($eg->id, fn () => (float) $settings->get('installation.fee_percent')));   // the default
        $this->assertEquals(5.0, (float) $settings->get('installation.fee_percent'));

        // Only payment, shipping, fee and installation settings can belong to one market.
        $this->expectException(\InvalidArgumentException::class);
        $settings->inMarket($eg->id, fn () => $settings->set('security.min_signup_seconds', 99));
    }

    public function test_a_market_with_unready_payments_does_not_stop_another_market_paying(): void
    {
        $settings = app(Settings::class);
        $settings->inMarket($this->egypt()->id, fn () => $settings->set('payments.active_provider', 'stripe'));   // not configured with keys

        $this->as('khaled.abdelrahman@demo.eg');
        $lantern = \App\Models\Product::where('slug', 'eg-lantern')->firstOrFail();
        $rule = $lantern->priceRules()->first();
        $order = $this->postJson('/api/orders', ['product_id' => $lantern->id, 'target_type' => $rule->target_type, 'targets' => [$rule->target_id],
            'shipping_address' => ['name' => 'K', 'phone' => '01012345678', 'line1' => '1 St', 'city' => 'Giza', 'country' => 'EG']])->assertCreated()->json('order');
        $this->postJson("/api/orders/{$order['id']}/payments", ['amount' => $order['total_amount']])->assertStatus(503);   // Egypt's own provider is not configured
    }

    public function test_a_market_manager_sees_and_changes_only_their_own_markets_settings(): void
    {
        $eg = $this->egypt();
        $br = $this->brazil();
        $this->as('owner.auto@demo.eg');

        $info = $this->getJson('/api/market')->assertOk()->json();
        $this->assertSame($eg->id, $info['id']);
        $this->assertStringContainsString("/api/webhooks/{provider}/{$eg->id}", $info['webhook_urls']['payments']);

        // Only market-ownable settings are listed, never the platform-wide ones.
        $keys = array_column($this->getJson('/api/market/settings')->assertOk()->json('settings'), 'key');
        $this->assertContains('installation.fee_percent', $keys);
        $this->assertContains('payments.active_provider', $keys);
        $this->assertNotContains('security.captcha_provider', $keys);
        $this->assertNotContains('platform.registration_open', $keys);

        $this->putJson('/api/market/settings', ['values' => ['installation.fee_percent' => 7]])->assertOk();
        $this->putJson('/api/market/settings', ['values' => ['security.min_signup_seconds' => 1]])->assertStatus(422);       // platform-wide
        $this->assertEquals(7.0, (float) app(Settings::class)->inMarket($eg->id, fn () => app(Settings::class)->get('installation.fee_percent')));
        $this->assertEquals(5.0, (float) app(Settings::class)->inMarket($br->id, fn () => app(Settings::class)->get('installation.fee_percent')));
        $this->assertEquals(5.0, (float) app(Settings::class)->get('installation.fee_percent'));

        // "Use the platform's value again".
        $this->deleteJson('/api/market/settings/installation.fee_percent')->assertOk();
        $this->assertEquals(5.0, (float) app(Settings::class)->inMarket($eg->id, fn () => app(Settings::class)->get('installation.fee_percent')));

        // A manager cannot reach the platform-wide admin screens, and the payments list holds only their market's.
        $this->getJson('/api/admin/settings')->assertForbidden();
        $this->getJson('/api/admin/markets')->assertForbidden();
        $this->getJson('/api/market/payments')->assertOk();
    }

    public function test_people_without_a_market_role_cannot_use_the_market_screens(): void
    {
        $this->as('nour.fawzy@demo.eg')->getJson('/api/market/settings')->assertForbidden();
        $this->as('seller.khan@demo.eg')->getJson('/api/market')->assertForbidden();
        $this->as('manager.auto1@demo.eg')->getJson('/api/market/settings')->assertForbidden();
    }

    public function test_the_platform_owner_edits_one_market_by_naming_it(): void
    {
        $br = $this->brazil();
        $this->as('admin@demo.eg');

        $this->putJson("/api/admin/settings?market={$br->id}", ['values' => ['installation.fee_percent' => 11]])->assertOk();
        $rows = collect($this->getJson("/api/admin/settings?market={$br->id}&group=installation")->json('settings'));
        $row = $rows->firstWhere('key', 'installation.fee_percent');
        $this->assertEquals(11.0, $row['value']);
        $this->assertTrue($row['market_override']);
        $this->assertFalse($rows->firstWhere('key', 'installation.warranty_days')['market_override']);               // inherits the platform's
        $this->assertEquals(5.0, collect($this->getJson('/api/admin/settings?group=installation')->json('settings'))->firstWhere('key', 'installation.fee_percent')['value']);
    }
}
