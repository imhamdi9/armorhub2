<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** "Bought together", "like this", picks from what was viewed, and the side-by-side comparison. */
class ShopRecommendationsTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private string $slug;
    private Product $pads;
    private Product $wipers;
    private Product $discs;
    private Product $filter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
        Cache::flush();
        $store = $this->customer->company;
        $store->update(['status' => 'active', 'settings' => ['storefront' => ['enabled' => true]]]);
        $this->slug = $store->slug;

        $make = fn (string $name, float $price, string $category = 'spare-parts', array $extra = []) => Product::create($extra + [
            'name' => $name, 'slug' => str($name)->slug(), 'category' => $category, 'base_price' => $price, 'is_active' => true,
            'industry_id' => $this->product->industry_id,
        ]);
        $this->pads = $make('Brake Pads', 100, 'spare-parts', ['attributes' => ['highlights' => ['Ceramic', 'Low dust'], 'page' => ['specs' => [['label' => 'Material', 'value' => 'Ceramic']]]], 'warranty_years' => 2]);
        $this->discs = $make('Brake Discs', 120);
        $this->wipers = $make('Wipers', 30, 'accessories');
        $this->filter = $make('Air Filter', 90);
    }

    private function buy(User $who, Product $product): void
    {
        Order::withoutGlobalScopes()->forceCreate(['company_id' => $this->customer->company_id, 'customer_id' => $who->id, 'product_id' => $product->id, 'status' => 'paid', 'product_amount' => 10, 'total_amount' => 10, 'currency' => 'USD']);
    }

    public function test_bought_together_needs_at_least_two_different_buyers_and_similar_follows_category_and_price(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->buy($a, $this->pads);
        $this->buy($a, $this->wipers);
        $page = fn () => $this->getJson("/api/public/storefronts/{$this->slug}/products/{$this->pads->id}")->assertOk()->json('recommended');

        $this->assertSame([], $page()['also_bought']);                                                         // one person's basket is not a pattern, and is never shown

        $this->buy($b, $this->pads);
        $this->buy($b, $this->wipers);
        Cache::flush();
        $this->assertSame(['Wipers'], collect($page()['also_bought'])->pluck('name')->all());

        $similar = collect($page()['similar'])->pluck('name')->all();
        $this->assertSame(['Air Filter', 'Brake Discs'], array_slice($similar, 0, 2));                          // same category, nearest price first
        $this->assertNotContains('Wipers', $similar);
        $this->assertNotContains('Brake Pads', $similar);
    }

    public function test_picks_come_from_the_categories_viewed_and_skip_what_was_seen(): void
    {
        $picks = fn (string $ids) => collect($this->getJson("/api/public/storefronts/{$this->slug}/recommendations?ids={$ids}")->assertOk()->json('products'))->pluck('name')->all();

        $this->assertSame([], $picks(''));
        $this->assertEqualsCanonicalizing(['Brake Discs', 'Air Filter'], $picks((string) $this->pads->id));
        $this->assertSame([], $picks((string) $this->wipers->id));                                              // nothing else in its category
    }

    public function test_the_comparison_keeps_the_order_asked_and_drops_what_is_not_for_sale(): void
    {
        $hidden = Product::create(['name' => 'Draft', 'slug' => 'draft', 'base_price' => 5, 'is_active' => false]);
        $ids = implode(',', [$this->discs->id, $hidden->id, $this->pads->id, 999999]);

        $rows = $this->getJson("/api/public/storefronts/{$this->slug}/products-by-ids?ids={$ids}&detail=1")->assertOk()->json('products');
        $this->assertSame(['Brake Discs', 'Brake Pads'], collect($rows)->pluck('name')->all());
        $this->assertSame(['Ceramic', 'Low dust'], $rows[1]['highlights']);
        $this->assertSame('Material', $rows[1]['specs'][0]['label']);
        $this->assertSame(2, $rows[1]['warranty_years']);

        // A comparison holds four at most; a plain list twelve.
        $many = collect(range(1, 6))->map(fn ($i) => Product::create(['name' => "P{$i}", 'slug' => "p{$i}", 'base_price' => $i, 'is_active' => true]))->pluck('id')->implode(',');
        $this->assertCount(4, $this->getJson("/api/public/storefronts/{$this->slug}/products-by-ids?ids={$many}&detail=1")->json('products'));
        $this->getJson("/api/public/storefronts/{$this->slug}/products-by-ids")->assertUnprocessable();
    }
}
