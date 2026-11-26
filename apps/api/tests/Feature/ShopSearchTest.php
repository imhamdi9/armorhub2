<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** A shop search that forgives typos, suggests as you type and narrows by price, brand and rating. */
class ShopSearchTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private string $slug;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
        Cache::flush();
        $store = $this->customer->company;
        $store->update(['status' => 'active', 'settings' => ['storefront' => ['enabled' => true]]]);
        $this->slug = $store->slug;

        foreach ([['Ceramic Brake Pads', 'Bosch', 120], ['Brake Disc Rotor', 'Brembo', 300], ['Wiper Blades', 'Bosch', 40], ['Cabin Air Filter', 'Mann', 70]] as [$name, $brand, $price]) {
            Product::create(['name' => $name, 'slug' => str($name)->slug(), 'brand' => $brand, 'category' => 'spare-parts', 'base_price' => $price, 'is_active' => true, 'industry_id' => $this->hood->industry_id ?? null]);
        }
    }

    private function names(string $query): array
    {
        return collect($this->getJson("/api/public/storefronts/{$this->slug}/products?{$query}")->assertOk()->json('data'))->pluck('name')->all();
    }

    public function test_every_word_must_match_and_a_typo_finds_the_closest_word(): void
    {
        $this->assertSame(['Ceramic Brake Pads'], $this->names('q=brake+pads'));           // both words, not either
        $this->assertSame(['Brake Disc Rotor', 'Ceramic Brake Pads'], $this->names('q=brake&sort=name'));

        $res = $this->getJson("/api/public/storefronts/{$this->slug}/products?q=brke+pads")->assertOk();
        $this->assertSame('brake pads', $res->json('search.corrected'));                    // the shop says what it searched for instead
        $this->assertSame(['Ceramic Brake Pads'], collect($res->json('data'))->pluck('name')->all());

        $none = $this->getJson("/api/public/storefronts/{$this->slug}/products?q=zzzzqqqq")->assertOk();
        $this->assertNull($none->json('search.corrected'));                                  // nothing close: no invented guess
        $this->assertSame(0, $none->json('total'));
    }

    public function test_suggestions_come_as_you_type_and_forgive_a_typo(): void
    {
        $s = $this->getJson("/api/public/storefronts/{$this->slug}/suggest?q=br")->assertOk()->json('suggestions');
        $texts = collect($s)->pluck('text')->all();
        $this->assertContains('Brembo', $texts);
        $this->assertContains('Brake Disc Rotor', $texts);
        $this->assertNotContains('Cabin Air Filter', $texts);
        $this->assertNotContains('Wiper Blades', $texts);
        $this->assertSame('brand', collect($s)->firstWhere('text', 'Brembo')['kind']);

        $typo = $this->getJson("/api/public/storefronts/{$this->slug}/suggest?q=wipr")->assertOk()->json('suggestions');
        $this->assertSame('Wiper Blades', $typo[0]['text']);

        $this->getJson("/api/public/storefronts/{$this->slug}/suggest?q=b")->assertOk()->assertJsonPath('suggestions', []);   // too short to say anything
    }

    public function test_price_brand_and_rating_filters_and_the_facets_that_offer_them(): void
    {
        $this->assertSame(['Cabin Air Filter', 'Wiper Blades'], $this->names('max_price=100&sort=name'));
        $this->assertSame(['Ceramic Brake Pads'], $this->names('min_price=100&max_price=200'));
        $this->assertSame(['Ceramic Brake Pads', 'Wiper Blades'], $this->names('brand=Bosch&sort=name'));

        $pads = Product::where('name', 'Ceramic Brake Pads')->first();
        $wipers = Product::where('name', 'Wiper Blades')->first();
        foreach ([[$pads, 5], [$wipers, 3]] as [$product, $stars]) {
            ProductReview::create(['product_id' => $product->id, 'user_id' => User::factory()->create()->id, 'rating' => $stars, 'status' => 'published']);
        }
        Cache::flush();
        $this->assertSame(['Ceramic Brake Pads'], $this->names('min_rating=4'));
        $this->assertSame(['Ceramic Brake Pads', 'Wiper Blades'], $this->names('min_rating=3&sort=rating'));          // best rated first

        $facets = $this->getJson("/api/public/storefronts/{$this->slug}/products")->assertOk()->json('facets');
        $this->assertSame(['name' => 'Bosch', 'count' => 2], $facets['brands'][0]);
        $this->assertEquals(40, $facets['price']['min']);
        $this->assertEquals(1000, $facets['price']['max']);
    }
}
