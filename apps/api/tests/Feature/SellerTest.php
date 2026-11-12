<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Company;
use App\Models\Order;
use App\Models\PartnerQuote;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class SellerTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private Company $sellerCo;
    private User $seller;
    private User $otherSeller;
    private Company $store;
    private array $address = ['name' => 'Layla', 'phone' => '0501234567', 'line1' => '12 King Road', 'city' => 'Riyadh', 'country' => 'SA'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
        $this->store = Company::withoutGlobalScopes()->where('name', 'Store')->first();
        $this->store->update(['slug' => 'main-store', 'settings' => ['storefront' => ['enabled' => true]]]);

        [$this->sellerCo, $this->seller] = $this->makeSeller('Gulf Parts', 'gulf@parts.example.org', $this->store->tenant_id);
        [, $this->otherSeller] = $this->makeSeller('Rival Parts', 'rival@parts.example.org', $this->store->tenant_id);
    }

    /** @return array{Company, User} */
    private function makeSeller(string $name, string $email, int $tenantId): array
    {
        $co = Company::create(['tenant_id' => $tenantId, 'name' => $name, 'type' => 'supplier', 'status' => 'active']);
        $u = User::factory()->create(['email' => $email, 'company_id' => $co->id]);
        $u->assignRole('seller');

        return [$co, $u];
    }

    private function listing(string $name = 'Brake pads'): int
    {
        return $this->actingAs($this->seller, 'sanctum')->postJson('/api/products', [
            'name' => $name, 'slug' => str($name)->slug().'-g', 'base_price' => 100, 'brand' => 'GulfBrand', 'category' => 'spare-parts',
            // None of these may be chosen by a seller:
            'industry_id' => 999, 'market' => 'EG', 'seller_id' => $this->otherSeller->company_id,
        ])->assertCreated()->json('id');
    }

    private function priced(int $productId): void
    {
        $this->actingAs($this->seller, 'sanctum')->putJson("/api/products/{$productId}/price-rules", ['rules' => [
            ['target_type' => 'vehicle_part', 'target_id' => $this->hood->id, 'price' => 150, 'is_active' => true],
        ]])->assertOk();
    }

    public function test_a_seller_lists_products_that_are_theirs_alone(): void
    {
        $id = $this->listing();
        $p = Product::find($id);

        $this->assertSame($this->sellerCo->id, $p->seller_id);                 // ownership is fixed by the server
        $this->assertSame($this->product->industry_id, $p->industry_id);        // the marketplace's industry
        $this->assertNull($p->market);                                          // marketplace has no country here
        $this->assertTrue((bool) $p->requires_shipping);                        // physical by default

        // Sellers see only their own products; the shared catalog is not theirs to change.
        $mine = collect($this->getJson('/api/products')->assertOk()->json())->pluck('id')->all();
        $this->assertSame([$id], $mine);
        $this->putJson("/api/products/{$this->product->id}", ['name' => 'Hijack'])->assertNotFound();
        $this->putJson("/api/products/{$this->product->id}/price-rules", ['rules' => []])->assertNotFound();

        // Another seller cannot see, change, price or delete it.
        $this->actingAs($this->otherSeller, 'sanctum');
        $this->assertSame([], $this->getJson('/api/products')->json());
        $this->getJson("/api/products/{$id}")->assertNotFound();
        $this->putJson("/api/products/{$id}", ['name' => 'Hijack'])->assertNotFound();
        $this->putJson("/api/products/{$id}/price-rules", ['rules' => []])->assertNotFound();
        $this->deleteJson("/api/products/{$id}")->assertNotFound();

        // A store owner (shared catalog) cannot edit a seller's product either; an admin can.
        $owner = User::factory()->create(['company_id' => $this->store->id]);
        $owner->assignRole('company-owner');
        $this->actingAs($owner, 'sanctum')->putJson("/api/products/{$id}", ['name' => 'Hijack'])->assertNotFound();
        $this->actingAs($this->admin, 'sanctum')->putJson("/api/products/{$id}", ['is_active' => false])->assertOk();
    }

    public function test_seller_products_reach_customers_only_while_the_seller_is_active(): void
    {
        $id = $this->listing();
        $this->priced($id);

        $names = fn () => collect($this->getJson('/api/public/storefronts/main-store/products')->json('data'))->pluck('name')->all();
        $this->assertContains('Brake pads', $names());
        $card = collect($this->getJson('/api/public/storefronts/main-store/products')->json('data'))->firstWhere('name', 'Brake pads');
        $this->assertSame('Gulf Parts', $card['seller']);

        // A seller of another marketplace never appears here.
        $tenant2 = Tenant::create(['name' => 'Other market', 'industry_id' => $this->product->industry_id]);
        [, $foreign] = $this->makeSeller('Foreign Parts', 'f@parts.example.org', $tenant2->id);
        $this->actingAs($foreign, 'sanctum')->postJson('/api/products', ['name' => 'Foreign thing', 'slug' => 'foreign-thing', 'base_price' => 1, 'category' => 'spare-parts'])->assertCreated();
        $this->assertNotContains('Foreign thing', $names());

        // Suspending the seller takes its products off sale everywhere.
        $this->sellerCo->update(['status' => 'inactive']);
        $this->assertNotContains('Brake pads', $names());
        $this->actingAs($this->customer, 'sanctum')->postJson('/api/orders', [
            'product_id' => $id, 'target_type' => 'vehicle_part', 'targets' => [$this->hood->id], 'shipping_address' => $this->address,
        ])->assertUnprocessable()->assertJsonValidationErrors('product_id');
    }

    public function test_a_sale_goes_straight_to_payment_then_the_seller_ships_it(): void
    {
        $id = $this->listing();
        $this->priced($id);

        // Direct sale: no location needed, no partners asked, ready to pay.
        $this->customer->update(['location_enabled' => false, 'latitude' => null, 'longitude' => null]);
        $orderId = $this->actingAs($this->customer->fresh(), 'sanctum')->postJson('/api/orders', [
            'product_id' => $id, 'target_type' => 'vehicle_part', 'targets' => [$this->hood->id], 'shipping_address' => $this->address,
        ])->assertCreated()->assertJsonPath('order.status', 'awaiting_payment')->assertJsonPath('partner_quotes', 0)->json('order.id');
        $this->assertSame(0, PartnerQuote::withoutGlobalScopes()->where('order_id', $orderId)->count());
        $this->assertEquals(150, Order::withoutGlobalScopes()->find($orderId)->total_amount);

        // The seller sees nothing until it is paid.
        $this->actingAs($this->seller, 'sanctum')->getJson('/api/seller/orders')->assertOk()->assertJsonCount(0, 'data');

        $this->actingAs($this->customer, 'sanctum')->postJson("/api/orders/{$orderId}/payments", ['amount' => 150])->assertCreated();
        $this->assertSame(OrderStatus::Paid, Order::withoutGlobalScopes()->find($orderId)->status);

        // Now the seller sees it with the address to ship to; a different seller does not.
        $list = $this->actingAs($this->seller, 'sanctum')->getJson('/api/seller/orders')->assertOk()->assertJsonCount(1, 'data');
        $this->assertStringNotContainsString('@', $list->getContent());   // no buyer email
        $this->getJson("/api/seller/orders/{$orderId}")->assertOk()->assertJsonPath('shipping_address.line1', '12 King Road')->assertJsonPath('customer_name', $this->customer->name);
        $this->assertSame(1, $this->seller->notifications()->where('data->kind', 'order_to_ship')->count());
        $this->actingAs($this->otherSeller, 'sanctum')->getJson("/api/seller/orders/{$orderId}")->assertNotFound();
        $this->getJson("/api/orders/{$orderId}/shipments")->assertNotFound();

        // The seller books the parcel with any carrier; delivery completes the sale.
        $ship = $this->actingAs($this->seller, 'sanctum')->postJson("/api/orders/{$orderId}/shipments", ['provider' => 'manual', 'carrier' => 'SMSA', 'tracking_number' => 'S1'])
            ->assertCreated()->json();
        $this->assertSame(1, $this->customer->notifications()->where('data->kind', 'shipped')->count());
        $this->postJson("/api/shipments/{$ship['id']}/status", ['status' => 'delivered'])->assertOk();
        $this->assertSame(OrderStatus::Completed, Order::withoutGlobalScopes()->find($orderId)->status);

        // The seller's home screen counts it.
        $cards = collect($this->getJson('/api/dashboard/stats')->assertOk()->json('cards'))->pluck('value', 'key');
        $this->assertEquals(1, $cards['delivered']);
        $this->assertEquals(1, $cards['products']);
    }

    public function test_a_seller_cannot_use_other_roles_screens(): void
    {
        $this->actingAs($this->seller, 'sanctum');
        foreach (['/api/admin/settings', '/api/partner-applications', '/api/commissions', '/api/partners', '/api/partner/orders', '/api/orders'] as $path) {
            $r = $this->getJson($path);
            $this->assertContains($r->status(), [403, 200, 422], $path);
        }
        $this->getJson('/api/admin/settings')->assertForbidden();
        $this->getJson('/api/partner-applications')->assertForbidden();
        $this->getJson('/api/commissions')->assertForbidden();
        $this->getJson('/api/partner/orders')->assertForbidden();
        // Orders (the customer API) never list other people's orders for a seller.
        $this->assertSame([], $this->getJson('/api/orders')->json('data'));
    }
}
