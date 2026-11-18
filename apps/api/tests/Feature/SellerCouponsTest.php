<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Order;
use App\Models\SellerLedgerEntry;
use App\Models\User;
use App\Services\Ledger\Accounts;
use App\Services\Ledger\Ledger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** A seller's own discount code: it works on that seller's goods only, and the seller (not the platform) pays for it. */
class SellerCouponsTest extends TestCase
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

    public function test_a_seller_makes_codes_that_only_they_can_see_and_change(): void
    {
        $mine = $this->listing($this->seller, 'Olive Soap', 200);
        $theirs = $this->listing($this->rivalSeller, 'Desk Lamp', 300);
        $this->actingAs($this->seller, 'sanctum')->postJson('/api/seller/promo-codes', ['code' => 'nile10', 'type' => 'percent', 'value' => 10])->assertUnprocessable()->assertJsonValidationErrors('product_ids');   // must choose products
        $this->postJson('/api/seller/promo-codes', ['code' => 'nile10', 'type' => 'percent', 'value' => 10, 'product_ids' => [$theirs]])->assertUnprocessable()->assertJsonValidationErrors('product_ids');       // only their own
        $this->postJson('/api/seller/promo-codes', ['code' => 'nile10', 'type' => 'percent', 'value' => 10, 'product_ids' => [$mine]])->assertCreated()->assertJsonPath('code', 'NILE10')->assertJsonPath('product_ids.0', $mine);
        $this->postJson('/api/seller/promo-codes', ['code' => 'NILE10', 'type' => 'percent', 'value' => 10, 'product_ids' => [$mine]])->assertUnprocessable();          // taken
        $this->postJson('/api/seller/promo-codes', ['code' => 'HUGE', 'type' => 'percent', 'value' => 95, 'product_ids' => [$mine]])->assertUnprocessable();            // too generous
        $this->getJson('/api/seller/promo-codes')->assertOk()->assertJsonCount(1);
        $this->actingAs($this->rivalSeller, 'sanctum')->getJson('/api/seller/promo-codes')->assertOk()->assertJsonCount(0);
        $id = \App\Models\PromoCode::value('id');
        $this->putJson("/api/seller/promo-codes/{$id}", ['is_active' => false])->assertNotFound();                                            // not theirs
        $this->actingAs($this->customer, 'sanctum')->getJson('/api/seller/promo-codes')->assertForbidden();
    }

    public function test_the_code_discounts_only_the_sellers_goods_and_the_seller_bears_it(): void
    {
        $mine = $this->listing($this->seller, 'Olive Soap', 200);
        $theirs = $this->listing($this->rivalSeller, 'Desk Lamp', 300);
        $other = $this->listing($this->seller, 'Hand Cream', 150);           // the seller's, but not one they chose for the code
        $this->actingAs($this->seller, 'sanctum')->postJson('/api/seller/promo-codes', ['code' => 'NILE10', 'type' => 'percent', 'value' => 10, 'product_ids' => [$mine]])->assertCreated();
        // A code for goods that are not in the cart is refused with a reason (the rival's, and the seller's own unchosen one).
        $this->actingAs($this->customer, 'sanctum')->postJson('/api/cart/items', ['product_id' => $theirs])->assertCreated();
        $this->postJson('/api/cart/items', ['product_id' => $other])->assertCreated();
        $this->postJson('/api/cart/promo', ['code' => 'NILE10'])->assertUnprocessable();
        $this->postJson('/api/cart/items', ['product_id' => $mine])->assertCreated();
        $this->postJson('/api/cart/promo', ['code' => 'NILE10'])->assertOk();
        $this->assertEquals(20, $this->getJson('/api/cart')->json('discount_amount'));                                                          // 10% of 200, not of 650

        $group = $this->postJson('/api/checkout', ['shipping_address' => $this->address])->assertCreated()->json();
        $this->postJson("/api/order-groups/{$group['id']}/payments", ['payment_method' => 'card'])->assertOk();
        $order = Order::withoutGlobalScopes()->where('product_id', $mine)->first();
        $rivalOrder = Order::withoutGlobalScopes()->where('product_id', $theirs)->first();
        $unchosen = Order::withoutGlobalScopes()->where('product_id', $other)->first();
        $this->assertEquals(20, $order->seller_discount_amount);
        $this->assertEquals(0, $rivalOrder->discount_amount);
        $this->assertEquals(0, $unchosen->discount_amount);               // the seller's other product got nothing

        // The seller's earnings carry the discount; the rival's are untouched; the platform's promo cost nets to zero.
        $this->assertEquals(-20, SellerLedgerEntry::where('order_id', $order->id)->where('type', 'discount')->value('amount'));
        $ledger = app(Ledger::class);
        $this->assertEqualsWithDelta(0, $ledger->balance(Accounts::EXP_PROMO, $order->currency), 0.001);
        $this->assertEquals((200 - 20 - 20) + (150 - 15), $this->actingAs($this->seller, 'sanctum')->getJson('/api/seller/earnings')->json('balances.'.$order->currency.'.held'));      // the coupon product: 200 less 10% commission and its own coupon; plus the other product: 150 less 10%
        $this->assertEquals(300 - 30, $this->actingAs($this->rivalSeller, 'sanctum')->getJson('/api/seller/earnings')->json('balances.'.$order->currency.'.held'));

        // A full refund gives the seller back what its own coupon cost it; a partial one is refused for such an order.
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/admin/orders/{$order->id}/partial-refund", ['amount' => 10, 'reason' => 'Scratch'])->assertUnprocessable();
        $this->postJson("/api/admin/orders/{$order->id}/refund", ['reason' => 'Returned'])->assertOk();
        $this->assertEqualsWithDelta(135, $ledger->balance(Accounts::sellerHeld($this->seller->company_id), $order->currency), 0.001);    // only the other product's earnings remain
        $this->assertEqualsWithDelta(0, $ledger->balance(Accounts::EXP_PROMO, $order->currency), 0.001);
    }
}
