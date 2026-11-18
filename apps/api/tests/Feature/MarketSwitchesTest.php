<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** The platform admin turns selling and installation centres on or off for each market; signup and shopping follow. */
class MarketSwitchesTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private Tenant $tenant;
    private int $productId;

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
        $this->productId = $this->postJson('/api/products', ['name' => 'Olive Soap', 'base_price' => 100, 'category' => $cat])->assertCreated()->json('id');
        $this->app['auth']->forgetGuards();
    }

    private function admin(): void
    {
        $this->app['auth']->forgetGuards();
        $this->actingAs(tap(User::factory()->create(), fn ($u) => $u->assignRole('platform-admin')), 'sanctum');
    }

    public function test_both_switches_start_on_and_the_admin_can_change_them_any_time(): void
    {
        $this->admin();
        $this->putJson("/api/admin/markets/{$this->tenant->id}", ['selling_enabled' => false])->assertOk()
            ->assertJsonPath('selling_enabled', false)->assertJsonPath('installation_enabled', true);
        $this->putJson("/api/admin/markets/{$this->tenant->id}", ['installation_enabled' => false])->assertOk()
            ->assertJsonPath('selling_enabled', false)->assertJsonPath('installation_enabled', false);
        $this->putJson("/api/admin/markets/{$this->tenant->id}", ['selling_enabled' => true])->assertOk()->assertJsonPath('selling_enabled', true);
    }

    public function test_a_market_can_be_opened_with_the_switches_already_chosen(): void
    {
        $this->admin();
        $industry = $this->tenant->industryIds()[0] ?? \App\Models\Industry::value('id');
        $id = $this->postJson('/api/admin/markets', ['country' => 'EG', 'industries' => [$industry], 'selling_enabled' => true, 'installation_enabled' => false])
            ->assertCreated()->assertJsonPath('installation_enabled', false)->assertJsonPath('selling_enabled', true)->json('id');
        $this->assertFalse((bool) Tenant::find($id)->installation_enabled);
    }

    public function test_with_selling_off_the_market_leaves_seller_signup_and_the_cart_refuses(): void
    {
        $this->app['auth']->forgetGuards();
        $this->assertNotEmpty($this->getJson('/api/public/marketplaces')->json('marketplaces'));
        $token = $this->postJson('/api/guest-session', ['store' => 'main-store'])->assertCreated()->json('token');

        $this->tenant->update(['selling_enabled' => false]);

        $this->assertSame([], $this->getJson('/api/public/marketplaces')->json('marketplaces'));
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/cart/items', ['product_id' => $this->productId, 'quantity' => 1])->assertUnprocessable();
    }

    public function test_the_store_list_tells_signup_which_markets_have_installation_centres(): void
    {
        $row = fn () => collect($this->getJson('/api/public/storefronts')->json('stores'))->firstWhere('slug', 'main-store');
        $this->assertTrue($row()['installation_enabled']);
        $this->tenant->update(['installation_enabled' => false]);
        $this->assertFalse($row()['installation_enabled']);
    }
}
