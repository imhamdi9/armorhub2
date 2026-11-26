<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Product;
use App\Models\ProductPriceRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** A seller's listing works the first time: buyable at once, an address made for them, no contact details, sane prices. */
class SellerListingFixesTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
        $store = Company::withoutGlobalScopes()->where('name', 'Store')->first();
        $store->update(['slug' => 'main-store', 'settings' => ['storefront' => ['enabled' => true]]]);
        $co = Company::create(['tenant_id' => $store->tenant_id, 'name' => 'Nile Goods', 'type' => 'supplier', 'status' => 'active']);
        $this->seller = tap(User::factory()->create(['company_id' => $co->id]), fn ($u) => $u->assignRole('seller'));
    }

    private function make(array $over = [])
    {
        return $this->actingAs($this->seller, 'sanctum')->postJson('/api/products', $over + ['name' => 'Olive Soap', 'base_price' => 120, 'category' => 'automotive-care']);
    }

    private function category(): string
    {
        return $this->actingAs($this->seller, 'sanctum')->getJson('/api/products/categories')->json('0.key');
    }

    public function test_a_new_product_is_buyable_at_its_base_price_without_any_extra_step(): void
    {
        $id = $this->make(['category' => $this->category()])->assertCreated()->json('id');
        $this->assertSame(1, ProductPriceRule::where('product_id', $id)->count());
        $this->actingAs($this->customer, 'sanctum')->postJson('/api/cart/items', ['product_id' => $id])->assertCreated();

        // Changing the price moves the lone price with it; a richer set the seller made is left alone.
        $this->actingAs($this->seller, 'sanctum')->putJson("/api/products/{$id}", ['base_price' => 150])->assertOk();
        $this->assertEquals(150, ProductPriceRule::where('product_id', $id)->value('price'));
    }

    public function test_the_address_is_made_for_the_seller_and_never_collides(): void
    {
        $cat = $this->category();
        $a = $this->make(['name' => 'Oud Perfume', 'name_ar' => 'عطر العود الفاخر', 'category' => $cat])->assertCreated()->json('slug');
        $b = $this->make(['name' => 'Oud Perfume', 'name_ar' => 'عطر العود الفاخر', 'category' => $cat])->assertCreated()->json('slug');
        $this->assertMatchesRegularExpression('/^[a-z0-9][a-z0-9-]*$/', $a);
        $this->assertNotSame($a, $b);
        $c = $this->make(['name' => 'Phone Case', 'slug' => 'film', 'category' => $cat])->assertCreated()->json('slug');     // "film" belongs to another product
        $this->assertNotSame('film', $c);
        $this->assertSame(3, Product::withoutGlobalScopes()->whereIn('slug', [$a, $b, $c])->count());
    }

    public function test_listings_keep_contact_details_out_and_prices_make_sense(): void
    {
        $cat = $this->category();
        $this->make(['category' => $cat, 'description' => 'Whatsapp me on 01012345678 for a better price'])->assertUnprocessable()->assertJsonValidationErrors('description');
        $this->make(['category' => $cat, 'base_price' => 0])->assertUnprocessable()->assertJsonValidationErrors('base_price');
        $this->make(['category' => $cat, 'compare_at_price' => 50])->assertUnprocessable()->assertJsonValidationErrors('compare_at_price');
        $this->make(['category' => $cat, 'name' => 'Brake pads 2019-2023', 'compare_at_price' => 200])->assertCreated();        // years in a name are not a phone number

        $id = $this->make(['category' => $cat, 'base_price' => '١٢٠٫٥'])->assertCreated()->json('id');                              // Arabic digits and decimal mark
        $this->assertEquals(120.5, Product::find($id)->base_price);
    }

    public function test_a_draft_is_published_only_with_a_photo_and_a_description(): void
    {
        $id = $this->make(['category' => $this->category(), 'is_active' => false])->assertCreated()->json('id');
        $this->putJson("/api/products/{$id}", ['is_active' => true])->assertUnprocessable()->assertJsonValidationErrors('is_active');
        $this->putJson("/api/products/{$id}", ['is_active' => true, 'description' => 'A well made product with a good finish.', 'images' => ['https://example.org/a.jpg']])->assertOk();
        $this->assertTrue((bool) Product::find($id)->is_active);
    }

    public function test_a_listing_in_two_languages_is_shown_in_the_shoppers_language(): void
    {
        $id = $this->make(['category' => $this->category(), 'name_ar' => 'صابون الزيتون', 'description_ar' => 'صابون طبيعي مصنوع من زيت الزيتون البكر.'])->assertCreated()->json('id');
        $this->putJson("/api/products/{$id}", ['description' => 'Natural soap made from virgin olive oil, cold pressed.'])->assertOk();
        $card = fn (string $lang) => $this->withHeaders(['Accept-Language' => $lang])->getJson("/api/public/storefronts/main-store/products/{$id}");

        $card('ar')->assertOk()->assertJsonPath('product.name', 'صابون الزيتون')->assertJsonPath('product.description', 'صابون طبيعي مصنوع من زيت الزيتون البكر.');
        $card('en')->assertOk()->assertJsonPath('product.name', 'Olive Soap');
        $this->assertStringContainsString('Accept-Language', (string) $card('en')->headers->get('Vary'));
        // Contact details are kept out of the Arabic copy too.
        $this->putJson("/api/products/{$id}", ['description_ar' => 'كلمني واتساب 01012345678'])->assertUnprocessable()->assertJsonValidationErrors('description_ar');
        // A product with no Arabic copy falls back to the main one.
        $other = $this->make(['category' => $this->category(), 'name' => 'Desk Lamp'])->assertCreated()->json('id');
        $this->withHeaders(['Accept-Language' => 'ar'])->getJson("/api/public/storefronts/main-store/products/{$other}")->assertOk()->assertJsonPath('product.name', 'Desk Lamp');
    }

    public function test_what_a_vehicle_part_fits_is_a_plain_optional_note_shown_to_shoppers(): void
    {
        $id = $this->make(['category' => $this->category()])->assertCreated()->json('id');
        $this->getJson("/api/public/storefronts/main-store/products/{$id}")->assertOk()->assertJsonPath('product.fits_note', null);            // never required

        $this->putJson("/api/products/{$id}", ['attributes' => ['fits_note' => 'Toyota Corolla 2018-2023 <b>only</b>']])->assertOk();
        $this->getJson("/api/public/storefronts/main-store/products/{$id}")->assertOk()->assertJsonPath('product.fits_note', 'Toyota Corolla 2018-2023 only');
        $this->putJson("/api/products/{$id}", ['attributes' => ['fits_note' => str_repeat('x', 301)]])->assertUnprocessable();
        $this->putJson("/api/products/{$id}", ['attributes' => ['fits_note' => '']])->assertOk();
        $this->getJson("/api/public/storefronts/main-store/products/{$id}")->assertJsonPath('product.fits_note', null);
    }
}
