<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** Nothing a seller makes is shown to shoppers until the catalogue team approves it. */
class ProductApprovalTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private User $seller;
    private User $reviewer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
        app(Settings::class)->set('products.require_approval', true);
        $store = Company::withoutGlobalScopes()->where('name', 'Store')->first();
        $store->update(['slug' => 'main-store', 'settings' => ['storefront' => ['enabled' => true]]]);
        $this->seller = tap(User::factory()->create(['company_id' => Company::create(['tenant_id' => $store->tenant_id, 'name' => 'Nile Goods', 'type' => 'supplier', 'status' => 'active'])->id]), fn ($u) => $u->assignRole('seller'));
        $this->reviewer = tap(User::factory()->create(['company_id' => $store->id]), fn ($u) => $u->assignRole('catalog-reviewer'));
    }

    private function shown(int $id): int
    {
        return $this->getJson("/api/public/storefronts/main-store/products/{$id}")->status();
    }

    private function draft(): int
    {
        $cat = $this->actingAs($this->seller, 'sanctum')->getJson('/api/products/categories')->json('0.key');

        return $this->postJson('/api/products', ['name' => 'Olive Soap', 'base_price' => 100, 'category' => $cat, 'is_active' => true])->assertCreated()->assertJsonPath('review_status', 'draft')->json('id');
    }

    public function test_a_new_product_is_a_hidden_draft_and_cannot_be_switched_on_by_the_seller(): void
    {
        $id = $this->draft();
        $this->assertSame(404, $this->shown($id));                                                                                         // asked for is_active, still hidden
        $this->putJson("/api/products/{$id}", ['is_active' => true])->assertUnprocessable()->assertJsonValidationErrors('is_active');
        $this->postJson("/api/products/{$id}/submit-review")->assertUnprocessable();                                                         // no photo, no description yet
    }

    public function test_the_catalogue_team_approves_or_sends_back_and_only_then_is_it_shown(): void
    {
        $id = $this->draft();
        $this->putJson("/api/products/{$id}", ['description' => 'A well made natural soap with a fresh scent.', 'images' => ['https://example.org/a.jpg']])->assertOk();
        $this->postJson("/api/products/{$id}/submit-review")->assertOk()->assertJsonPath('review_status', 'pending');
        $this->assertSame(404, $this->shown($id));
        $this->assertNotNull($this->reviewer->notifications()->get()->first(fn ($n) => str_contains($n->data['title'] ?? '', 'waits for review')));

        // Only the market's reviewers see it; the seller and customers cannot decide.
        $this->actingAs($this->customer, 'sanctum')->getJson('/api/admin/product-reviews')->assertForbidden();
        $this->actingAs($this->seller, 'sanctum')->postJson("/api/admin/product-reviews/{$id}/approve")->assertForbidden();
        $far = tap(User::factory()->create(['company_id' => Company::create(['tenant_id' => Tenant::create(['name' => 'Elsewhere'])->id, 'name' => 'Far', 'type' => 'supplier', 'status' => 'active'])->id]), fn ($u) => $u->assignRole('catalog-reviewer'));
        $this->actingAs($far, 'sanctum')->getJson('/api/admin/product-reviews')->assertOk()->assertJsonCount(0);
        $this->postJson("/api/admin/product-reviews/{$id}/approve")->assertNotFound();

        $this->actingAs($this->reviewer, 'sanctum')->getJson('/api/admin/product-reviews')->assertOk()->assertJsonCount(1)->assertJsonPath('0.name', 'Olive Soap');
        $this->postJson("/api/admin/product-reviews/{$id}/reject", ['note' => ''])->assertUnprocessable();
        $this->postJson("/api/admin/product-reviews/{$id}/reject", ['note' => 'The photo is blurry, please upload a clearer one.'])->assertOk()->assertJsonPath('status', 'rejected');
        $this->assertSame(404, $this->shown($id));
        $told = $this->seller->notifications()->get()->first(fn ($n) => str_contains($n->data['title'] ?? '', 'needs changes'));
        $this->assertStringContainsString('blurry', $told->data['body']);

        $this->actingAs($this->seller, 'sanctum')->postJson("/api/products/{$id}/submit-review")->assertOk()->assertJsonPath('review_status', 'pending');
        $this->actingAs($this->reviewer, 'sanctum')->postJson("/api/admin/product-reviews/{$id}/approve")->assertOk()->assertJsonPath('status', 'approved');
        $this->assertSame(200, $this->shown($id));
        $this->postJson("/api/admin/product-reviews/{$id}/approve")->assertUnprocessable();                                                  // decided once
    }

    public function test_changing_what_shoppers_read_sends_an_approved_product_back_but_a_price_change_does_not(): void
    {
        $id = $this->draft();
        Product::withoutGlobalScopes()->whereKey($id)->update(['review_status' => 'approved', 'is_active' => true, 'description' => 'A well made natural soap with a fresh scent.', 'images' => ['https://example.org/a.jpg']]);
        $this->assertSame(200, $this->shown($id));

        $this->actingAs($this->seller, 'sanctum')->putJson("/api/products/{$id}", ['base_price' => 120])->assertOk();
        $this->assertSame(200, $this->shown($id));                                                                                           // a price is not reviewed
        $this->putJson("/api/products/{$id}", ['is_active' => false])->assertOk();                                                           // the seller may hide and show it
        $this->putJson("/api/products/{$id}", ['is_active' => true])->assertOk();
        $this->putJson("/api/products/{$id}", ['description' => 'Now it is a different product altogether, with new claims.'])->assertOk();
        $this->assertSame(404, $this->shown($id));
        $this->assertSame('pending', Product::withoutGlobalScopes()->find($id)->review_status);
    }

    public function test_a_bundle_waits_for_review_too(): void
    {
        $cat = $this->actingAs($this->seller, 'sanctum')->getJson('/api/products/categories')->json('0.key');
        $mk = fn (string $n) => $this->postJson('/api/products', ['name' => $n, 'base_price' => 100, 'category' => $cat, 'images' => ['https://example.org/a.jpg'], 'description' => 'A well made product with a fresh scent.'])->assertCreated()->json('id');
        $a = $mk('Olive Soap');
        $b = $mk('Hand Cream');
        $id = $this->postJson('/api/seller/bundles', ['name' => 'Care set', 'name_ar' => 'طقم العناية', 'description' => 'Soap and cream together, a simple care routine.', 'description_ar' => 'صابون وكريم معاً لروتين عناية بسيط.',
            'base_price' => 150, 'items' => [['product_id' => $a, 'quantity' => 1], ['product_id' => $b, 'quantity' => 1]]])->assertCreated()->assertJsonPath('review_status', 'pending')->json('id');
        $this->assertSame(404, $this->shown($id));
        $this->actingAs($this->reviewer, 'sanctum')->postJson("/api/admin/product-reviews/{$id}/approve")->assertOk();
        $this->assertSame(200, $this->shown($id));
    }
}
