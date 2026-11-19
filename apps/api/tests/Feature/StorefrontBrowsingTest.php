<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use App\Support\CategoryTaxonomy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** Browsing a market: departments with their categories (each once), a department showing everything under it, Arabic search, deals and platform-shipped filters. */
class StorefrontBrowsingTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
        CategoryTaxonomy::ensureDefault();
        $store = Company::withoutGlobalScopes()->where('name', 'Store')->first();
        $store->update(['slug' => 'main-store', 'settings' => ['storefront' => ['enabled' => true]]]);
        $seller = tap(User::factory()->create(['company_id' => Company::create(['tenant_id' => $store->tenant_id, 'name' => 'Nile Goods', 'type' => 'supplier', 'status' => 'active'])->id]), fn ($u) => $u->assignRole('seller'));
        $this->actingAs($seller, 'sanctum');
        $cat = $this->getJson('/api/products/categories')->json('0.key');
        foreach ([['Power bank', 'accessories'], ['Fast charger', 'accessories'], ['Olive soap', 'natural-care']] as [$name, $category]) {
            $this->ids[$name] = $this->postJson('/api/products', ['name' => $name, 'base_price' => 100, 'category' => $cat])->assertCreated()->json('id');
            Product::withoutGlobalScopes()->whereKey($this->ids[$name])->update(['category' => $category]);
        }
        Product::withoutGlobalScopes()->whereKey($this->ids['Power bank'])->update(['name_ar' => 'باور بانك سريع', 'compare_at_price' => 150]);
        $this->app['auth']->forgetGuards();
        \Illuminate\Support\Facades\Cache::flush();
    }

    public function test_each_category_is_listed_once_and_departments_hold_their_categories(): void
    {
        $json = $this->getJson('/api/public/storefronts/main-store')->assertOk()->json();

        $this->assertSame(['accessories', 'natural-care'], $json['categories']);                     // two products in "accessories": still once
        $tree = collect($json['category_tree']);
        $auto = $tree->firstWhere('key', 'automotive');
        $this->assertSame(2, $auto['count']);
        $this->assertSame([['key' => 'accessories', 'name' => 'Accessories', 'count' => 2]], $auto['children']);
        $this->assertSame(1, $tree->firstWhere('key', 'beauty')['count']);
        $this->assertSame($tree->all(), $this->getJson('/api/public/storefronts/main-store/categories')->json('tree'));     // the light version for the menu
    }

    public function test_choosing_a_department_shows_everything_filed_under_it(): void
    {
        $this->assertSame(2, $this->getJson('/api/public/storefronts/main-store/products?category=automotive')->json('total'));
        $this->assertSame(2, $this->getJson('/api/public/storefronts/main-store/products?category=accessories')->json('total'));
        $this->assertSame(1, $this->getJson('/api/public/storefronts/main-store/products?category=beauty')->json('total'));
    }

    public function test_the_arabic_name_is_searchable_and_deals_are_filterable(): void
    {
        $found = $this->getJson('/api/public/storefronts/main-store/products?q='.urlencode('باور'))->assertOk();
        $this->assertSame([$this->ids['Power bank']], collect($found->json('data'))->pluck('id')->all());

        $this->assertSame([$this->ids['Power bank']], collect($this->getJson('/api/public/storefronts/main-store/products?deals=1')->json('data'))->pluck('id')->all());
        $this->assertSame(0, $this->getJson('/api/public/storefronts/main-store/products?stored=1')->json('total'));             // nothing is kept in the platform's warehouse yet
        $this->assertGreaterThanOrEqual(3, $this->getJson('/api/public/storefronts/main-store/products')->json('total'));
    }
}
