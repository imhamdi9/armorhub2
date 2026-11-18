<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** The checklist that tells a seller what a listing still needs before shoppers trust it. */
class ProductReadinessTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
        $store = Company::withoutGlobalScopes()->where('name', 'Store')->first();
        $co = Company::create(['tenant_id' => $store->tenant_id, 'name' => 'Gulf Parts', 'type' => 'supplier', 'status' => 'active']);
        $this->seller = User::factory()->create(['email' => 'gulf@parts.example.org', 'company_id' => $co->id]);
        $this->seller->assignRole('seller');
    }

    public function test_a_bare_listing_shows_what_is_missing_and_a_complete_one_is_ready(): void
    {
        $this->actingAs($this->seller, 'sanctum');
        $id = $this->postJson('/api/products', ['name' => 'Brake pads', 'slug' => 'brake-pads-r', 'base_price' => 100, 'category' => 'spare-parts', 'requires_shipping' => true])->assertCreated()->json('id');

        $bare = $this->getJson("/api/products/{$id}/readiness")->assertOk()->json();
        $this->assertFalse($bare['ready']);
        $missing = collect($bare['items'])->where('ok', false)->pluck('key')->all();
        foreach (['photos', 'description', 'highlights', 'brand', 'warranty', 'stock', 'parcel'] as $key) {
            $this->assertContains($key, $missing);
        }
        $this->assertNotContains('price', $missing);                                              // it has a price
        $this->assertLessThan(50, $bare['score']);

        // The list carries the score so a seller can sort out the worst first.
        $row = collect($this->getJson('/api/products')->json())->firstWhere('id', $id);
        $this->assertSame($bare['score'], $row['readiness']['score']);
        $this->assertSame($bare['total'] - $bare['done'], $row['readiness']['missing']);

        $this->putJson("/api/products/{$id}", [
            'name' => 'Brake pads', 'slug' => 'brake-pads-r', 'base_price' => 100, 'category' => 'spare-parts', 'requires_shipping' => true,
            'brand' => 'GulfBrand', 'sku' => 'BP-1', 'warranty_years' => 2, 'stock_cartons' => 10, 'units_per_carton' => 4,
            'carton_length_cm' => 30, 'carton_width_cm' => 20, 'carton_height_cm' => 10, 'carton_weight_kg' => 3,
            'description' => 'Ceramic brake pads for everyday driving: quiet, low dust, and a firm bite from the first stop to the last.',
            'images' => ['https://img.example.org/a.jpg', 'https://img.example.org/b.jpg', 'https://img.example.org/c.jpg'],
            'attributes' => ['highlights' => ['Ceramic compound', 'Low dust', 'Sold as a set of four']],
        ])->assertOk();

        $done = $this->getJson("/api/products/{$id}/readiness")->assertOk()->json();
        $this->assertTrue($done['ready'], json_encode(collect($done['items'])->where('ok', false)->all()));
        $this->assertSame(100, $done['score']);
    }

    public function test_only_people_who_manage_the_product_may_read_its_checklist(): void
    {
        $id = $this->actingAs($this->seller, 'sanctum')->postJson('/api/products', ['name' => 'Wipers', 'slug' => 'wipers-r', 'base_price' => 20, 'category' => 'spare-parts'])->assertCreated()->json('id');

        $this->actingAs($this->customer, 'sanctum')->getJson("/api/products/{$id}/readiness")->assertForbidden();
        $other = User::factory()->create(['email' => 'rival@parts.example.org', 'company_id' => Company::create(['tenant_id' => $this->seller->company->tenant_id, 'name' => 'Rival', 'type' => 'supplier', 'status' => 'active'])->id]);
        $other->assignRole('seller');
        $this->actingAs($other, 'sanctum')->getJson("/api/products/{$id}/readiness")->assertNotFound();    // a rival's listing is not theirs to inspect
    }
}
