<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** Several of a seller's own products sold together at one price, bought and returned like any product. */
class SellerBundlesTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private User $seller;
    private User $rivalSeller;
    private array $address = ['name' => 'Layla', 'phone' => '0501234567', 'line1' => '12 King Road', 'city' => 'Riyadh'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
        $store = Company::withoutGlobalScopes()->where('name', 'Store')->first();
        $store->update(['slug' => 'main-store', 'settings' => ['storefront' => ['enabled' => true]]]);
        $mk = fn (string $name) => tap(User::factory()->create(['company_id' => Company::create(['tenant_id' => $store->tenant_id, 'name' => $name, 'type' => 'supplier', 'status' => 'active'])->id]), fn ($u) => $u->assignRole('seller'));
        $this->seller = $mk('Nile Goods');
        $this->rivalSeller = $mk('Delta Goods');
    }

    private function listing(User $by, string $name, int $price): int
    {
        $cat = $this->actingAs($by, 'sanctum')->getJson('/api/products/categories')->json('0.key');

        return $this->postJson('/api/products', ['name' => $name, 'base_price' => $price, 'category' => $cat])->assertCreated()->json('id');
    }

    public function test_a_bundle_is_made_of_the_sellers_own_products_at_one_price(): void
    {
        $a = $this->listing($this->seller, 'Olive Soap', 100);
        $b = $this->listing($this->seller, 'Hand Cream', 150);
        $theirs = $this->listing($this->rivalSeller, 'Desk Lamp', 300);

        $this->actingAs($this->seller, 'sanctum')->postJson('/api/seller/bundles', ['name' => 'Care set', 'name_ar' => 'طقم العناية', 'description' => 'A set of care products for every day.', 'description_ar' => 'طقم من منتجات العناية لكل يوم، بسعر واحد.', 'base_price' => 220, 'items' => [['product_id' => $a, 'quantity' => 1]]])->assertUnprocessable();             // two parts at least
        $this->postJson('/api/seller/bundles', ['name' => 'Care set', 'name_ar' => 'طقم العناية', 'description' => 'A set of care products for every day.', 'description_ar' => 'طقم من منتجات العناية لكل يوم، بسعر واحد.', 'base_price' => 220, 'items' => [['product_id' => $a, 'quantity' => 1], ['product_id' => $theirs, 'quantity' => 1]]])->assertUnprocessable();   // not theirs
        $made = $this->postJson('/api/seller/bundles', ['name' => 'Care set', 'name_ar' => 'طقم العناية', 'description' => 'A set of care products for every day.', 'description_ar' => 'طقم من منتجات العناية لكل يوم، بسعر واحد.', 'base_price' => 220, 'items' => [['product_id' => $a, 'quantity' => 1], ['product_id' => $b, 'quantity' => 2]]])->assertCreated();
        $made->assertJsonPath('worth', 400);                                                                                                                                                                       // 100 + 2 x 150
        $id = $made->json('id');
        $this->postJson('/api/seller/bundles', ['name' => 'Dear set', 'name_ar' => 'طقم غالٍ', 'description' => 'A set of care products for every day.', 'description_ar' => 'طقم من منتجات العناية لكل يوم، بسعر واحد.', 'base_price' => 400, 'items' => [['product_id' => $a, 'quantity' => 1], ['product_id' => $b, 'quantity' => 2]]])->assertUnprocessable()->assertJsonValidationErrors('base_price');   // not a saving
        $this->postJson('/api/seller/bundles', ['name' => 'Nested', 'name_ar' => 'متداخل', 'description' => 'A set of care products for every day.', 'description_ar' => 'طقم من منتجات العناية لكل يوم، بسعر واحد.', 'base_price' => 300, 'items' => [['product_id' => $id, 'quantity' => 1], ['product_id' => $a, 'quantity' => 1]]])->assertUnprocessable();       // no bundle inside a bundle

        // The shopper sees what is inside, in their language, with the saving as the "was" price.
        $page = $this->withHeaders(['Accept-Language' => 'ar'])->getJson("/api/public/storefronts/main-store/products/{$id}")->assertOk();
        $page->assertJsonPath('product.name', 'طقم العناية')->assertJsonPath('product.compare_at_price', 400)->assertJsonCount(2, 'product.bundle_items');

        // It is bought, paid and refunded like any product; the books balance (checked after every test).
        $this->actingAs($this->customer, 'sanctum')->postJson('/api/cart/items', ['product_id' => $id])->assertCreated();
        $group = $this->postJson('/api/checkout', ['shipping_address' => $this->address])->assertCreated()->json();
        $this->postJson("/api/order-groups/{$group['id']}/payments", ['payment_method' => 'card'])->assertOk();
        $order = Order::withoutGlobalScopes()->where('product_id', $id)->firstOrFail();
        $this->assertEquals(220, $order->total_amount);
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/admin/orders/{$order->id}/refund", ['reason' => 'Returned'])->assertOk();

        // A part of a bundle cannot move to the warehouse while it is in one.
        $this->actingAs($this->seller, 'sanctum')->putJson("/api/products/{$a}", ['fulfilment' => 'armorhub'])->assertUnprocessable();
        $this->putJson("/api/seller/bundles/{$id}", ['base_price' => 200])->assertOk()->assertJsonPath('base_price', 200);
        $this->actingAs($this->rivalSeller, 'sanctum')->putJson("/api/seller/bundles/{$id}", ['base_price' => 1])->assertNotFound();
    }
}
