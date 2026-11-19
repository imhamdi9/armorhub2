<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use App\Support\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** Every product speaks both languages: English copy for the English platform, Arabic copy for the Arabic one, never a mix. */
class SellerLanguagesTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
        app(Settings::class)->set('products.require_both_languages', true);
        $store = Company::withoutGlobalScopes()->where('name', 'Store')->first();
        $store->update(['slug' => 'main-store', 'settings' => ['storefront' => ['enabled' => true]]]);
        $co = Company::create(['tenant_id' => $store->tenant_id, 'name' => 'Nile Goods', 'type' => 'supplier', 'status' => 'active']);
        $this->seller = tap(User::factory()->create(['company_id' => $co->id]), fn ($u) => $u->assignRole('seller'));
    }

    private function full(array $over = []): array
    {
        $cat = $this->actingAs($this->seller, 'sanctum')->getJson('/api/products/categories')->json('0.key');

        return $over + ['name' => 'Olive Soap', 'name_ar' => 'صابون الزيتون', 'description' => 'Natural soap made from virgin olive oil.', 'description_ar' => 'صابون طبيعي من زيت الزيتون البكر.', 'base_price' => 100, 'category' => $cat];
    }

    public function test_a_new_product_needs_its_name_and_description_in_both_languages(): void
    {
        $this->actingAs($this->seller, 'sanctum');
        foreach (['name_ar', 'description', 'description_ar'] as $missing) {
            $payload = $this->full();
            unset($payload[$missing]);
            $this->postJson('/api/products', $payload)->assertUnprocessable()->assertJsonValidationErrors($missing);
        }
        $this->postJson('/api/products', $this->full())->assertCreated();
    }

    public function test_each_copy_must_be_in_its_own_language(): void
    {
        $this->actingAs($this->seller, 'sanctum');
        $this->postJson('/api/products', $this->full(['name' => 'صابون الزيتون']))->assertUnprocessable()->assertJsonValidationErrors('name');                // Arabic in the English field
        $this->postJson('/api/products', $this->full(['description_ar' => 'Natural olive soap']))->assertUnprocessable()->assertJsonValidationErrors('description_ar');   // English in the Arabic field
    }

    public function test_the_shopper_gets_one_language_throughout(): void
    {
        $id = $this->actingAs($this->seller, 'sanctum')->postJson('/api/products', $this->full())->assertCreated()->json('id');
        $page = fn (string $l) => $this->withHeaders(['Accept-Language' => $l])->getJson("/api/public/storefronts/main-store/products/{$id}");
        $page('ar')->assertJsonPath('product.name', 'صابون الزيتون')->assertJsonPath('product.description', 'صابون طبيعي من زيت الزيتون البكر.');
        $page('en')->assertJsonPath('product.name', 'Olive Soap')->assertJsonPath('product.description', 'Natural soap made from virgin olive oil.');
    }

    public function test_a_draft_cannot_be_published_while_a_language_is_missing(): void
    {
        $id = Product::withoutGlobalScopes()->create(['seller_id' => $this->seller->company_id, 'name' => 'Draft', 'slug' => 'draft-x', 'base_price' => 5, 'category' => 'x', 'is_active' => false, 'industry_id' => $this->product->industry_id, 'images' => ['https://example.org/a.jpg'], 'description' => 'An English description only here.'])->id;
        $this->actingAs($this->seller, 'sanctum')->putJson("/api/products/{$id}", ['is_active' => true])->assertUnprocessable();
        $this->putJson("/api/products/{$id}", ['is_active' => true, 'name_ar' => 'مسودة', 'description_ar' => 'وصف عربي كامل للمنتج.'])->assertOk();
    }
}
