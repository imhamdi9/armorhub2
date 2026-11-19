<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Company;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class WarehouseFulfilmentTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private Company $store;
    private Company $sellerCo;
    private User $seller;
    private User $staff;
    private array $address = ['name' => 'Layla', 'phone' => '0501234567', 'line1' => '12 King Road', 'city' => 'Riyadh', 'country' => 'SA'];
    private array $carton = ['units_per_carton' => 10, 'carton_length_cm' => 40, 'carton_width_cm' => 30, 'carton_height_cm' => 20, 'carton_weight_kg' => 5];

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
        $this->store = Company::withoutGlobalScopes()->where('name', 'Store')->first();
        $this->store->update(['slug' => 'main-store', 'settings' => ['storefront' => ['enabled' => true]]]);
        $this->sellerCo = Company::create(['tenant_id' => $this->store->tenant_id, 'name' => 'Gulf Parts', 'type' => 'supplier', 'status' => 'active']);
        $this->seller = User::factory()->create(['company_id' => $this->sellerCo->id]);
        $this->seller->assignRole('seller');
        $this->staff = User::factory()->create(['company_id' => $this->store->id]);
        $this->staff->assignRole('warehouse-staff');
    }

    private function listed(array $extra = []): int
    {
        return $this->actingAs($this->seller, 'sanctum')->postJson('/api/products', [
            'name' => 'Brake pads', 'slug' => 'brake-pads-w', 'base_price' => 100, 'category' => 'spare-parts', 'requires_shipping' => true,
        ] + $extra)->assertCreated()->json('id');
    }

    public function test_a_seller_can_only_choose_armorhub_storage_when_the_product_and_the_market_are_ready(): void
    {
        $id = $this->listed();

        // No carton data, no warehouse: refused with reasons.
        $this->putJson("/api/products/{$id}", ['fulfilment' => 'armorhub'])->assertStatus(422)->assertJsonValidationErrors('fulfilment');
        $this->putJson("/api/products/{$id}", ['fulfilment' => 'armorhub'] + $this->carton)->assertStatus(422)->assertJsonPath('errors.fulfilment.0', 'This market has no open warehouse yet.');

        $this->actingAs($this->staff, 'sanctum')->postJson('/api/warehouse/warehouses', ['name' => 'Riyadh DC', 'city' => 'Riyadh'])->assertCreated();
        $this->actingAs($this->seller, 'sanctum')->putJson("/api/products/{$id}", ['fulfilment' => 'armorhub'] + $this->carton)->assertOk()->assertJson(['fulfilment' => 'armorhub']);
    }

    public function test_cartons_arrive_stock_is_counted_and_the_warehouse_ships_the_order(): void
    {
        $id = $this->listed($this->carton);
        $this->actingAs($this->staff, 'sanctum')->postJson('/api/warehouse/warehouses', ['name' => 'Riyadh DC'])->assertCreated();
        $wh = \App\Models\Warehouse::first();
        $this->actingAs($this->seller, 'sanctum')->putJson("/api/products/{$id}", ['fulfilment' => 'armorhub'])->assertOk();
        // Priced so it can be bought.
        $this->putJson("/api/products/{$id}/price-rules", ['rules' => [['target_type' => 'vehicle_part', 'target_id' => $this->hood->id, 'price' => 150, 'is_active' => true]]])->assertOk();

        // Out of stock: it cannot be ordered, and the public card says so.
        $this->customer->update(['location_enabled' => false, 'latitude' => null, 'longitude' => null]);
        $order = ['product_id' => $id, 'target_type' => 'vehicle_part', 'targets' => [$this->hood->id], 'shipping_address' => $this->address];
        $this->actingAs($this->customer, 'sanctum')->postJson('/api/orders', $order)->assertStatus(422)->assertJsonValidationErrors('product_id');
        $card = $this->getJson("/api/public/storefronts/main-store/products/{$id}")->json('product');
        $this->assertTrue($card['fulfilled_by_armorhub']);
        $this->assertFalse($card['in_stock']);

        // The seller announces 3 cartons; only things it chose to have stored can be sent.
        $own = $this->actingAs($this->seller, 'sanctum')->postJson('/api/seller/inbound-shipments', ['items' => [['product_id' => $id, 'cartons' => 3]], 'carrier' => 'SMSA'])->assertCreated()->json();
        $plain = Product::create(['name' => 'Own ship', 'slug' => 'own-ship', 'base_price' => 1, 'category' => 'x', 'seller_id' => $this->sellerCo->id])->id;
        $this->postJson('/api/seller/inbound-shipments', ['items' => [['product_id' => $plain, 'cartons' => 1]]])->assertStatus(422);

        // A customer saved it while it was out of stock: they are told when it is back.
        \DB::table('favourites')->insert(['user_id' => $this->customer->id, 'product_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
        $told = $this->customer->notifications()->count();

        // The warehouse counts 2 cartons (one was missing): 20 units are in stock. Receiving twice is refused.
        $line = $own['items'][0]['id'];
        $this->actingAs($this->staff, 'sanctum')->postJson("/api/warehouse/inbound/{$own['id']}/receive", ['items' => [['id' => $line, 'cartons_received' => 2]]])->assertStatus(422);   // a short count needs a reason
        $this->postJson("/api/warehouse/inbound/{$own['id']}/receive", ['items' => [['id' => $line, 'cartons_received' => 2, 'note' => 'One carton crushed']]])->assertOk()->assertJsonPath('status', 'received');
        $diffNote = $this->seller->notifications()->get()->first(fn ($n) => str_contains($n->data['title'] ?? '', 'differences'));   // the seller hears exactly what was short
        $this->assertNotNull($diffNote);
        $this->assertStringContainsString('3 → 2 (One carton crushed)', $diffNote->data['body']);
        $this->postJson("/api/warehouse/inbound/{$own['id']}/receive", ['items' => [['id' => $line, 'cartons_received' => 2]]])->assertStatus(422);
        $this->assertSame(20, (int) \DB::table('warehouse_stock')->where('product_id', $id)->value('units'));
        $this->assertSame($told + 1, $this->customer->notifications()->count());                      // "back in stock"
        $this->assertNull($this->getJson("/api/public/storefronts/main-store/products/{$id}")->json('product.units_left'));   // plenty left
        app(\App\Services\Warehouse\WarehouseService::class)->move($wh->id, $id, -17, 'adjustment');
        $this->assertSame(3, $this->getJson("/api/public/storefronts/main-store/products/{$id}")->json('product.units_left'));  // running low
        app(\App\Services\Warehouse\WarehouseService::class)->move($wh->id, $id, 17, 'adjustment');
        $this->assertSame($told + 1, $this->customer->notifications()->count());                      // topping up what was not empty tells no one
        $this->actingAs($this->seller, 'sanctum')->getJson('/api/seller/stock')->assertOk()->assertJsonPath('products.0.units', 20);

        // Now it can be bought; the order is stamped for the warehouse and the seller can NOT ship it.
        $orderId = $this->actingAs($this->customer, 'sanctum')->postJson('/api/orders', $order)->assertCreated()->json('order.id');
        $this->postJson("/api/orders/{$orderId}/payments", ['amount' => 150])->assertCreated();
        $o = Order::withoutGlobalScopes()->find($orderId);
        $this->assertSame('armorhub', $o->fulfilment);
        $this->assertSame($wh->id, $o->warehouse_id);
        $this->actingAs($this->seller, 'sanctum')->postJson("/api/orders/{$orderId}/shipments", ['provider' => 'manual', 'carrier' => 'X'])->assertNotFound();

        // The warehouse sees it to pack, ships it, and one unit leaves stock with a recorded movement.
        $this->actingAs($this->staff, 'sanctum')->getJson('/api/warehouse/orders')->assertOk()->assertJsonCount(1)->assertJsonPath('0.shipping_address.line1', '12 King Road');
        $this->postJson("/api/warehouse/orders/{$orderId}/ship", ['carrier' => 'Aramex', 'tracking_number' => 'A1'])->assertCreated();
        $this->assertSame(19, (int) \DB::table('warehouse_stock')->where('product_id', $id)->value('units'));
        $this->assertSame(['received', 'shipped'], \DB::table('stock_movements')->where('type', '!=', 'adjustment')->orderBy('id')->pluck('type')->all());
        $this->getJson('/api/warehouse/orders')->assertJsonCount(0);
        $this->assertSame(1, $this->customer->notifications()->where('data->kind', 'shipped')->count());

        // A correction needs a reason and cannot take more than is there.
        $this->postJson('/api/warehouse/stock/adjust', ['warehouse_id' => $wh->id, 'product_id' => $id, 'units' => -5, 'note' => 'Damaged'])->assertOk()->assertJson(['units' => 14]);
        $this->postJson('/api/warehouse/stock/adjust', ['warehouse_id' => $wh->id, 'product_id' => $id, 'units' => -500, 'note' => 'x'])->assertStatus(422);
        $this->postJson('/api/warehouse/stock/adjust', ['warehouse_id' => $wh->id, 'product_id' => $id, 'units' => -1])->assertStatus(422);
    }

    public function test_the_last_unit_is_promised_to_one_buyer_only(): void
    {
        $id = $this->listed($this->carton);
        $this->actingAs($this->staff, 'sanctum')->postJson('/api/warehouse/warehouses', ['name' => 'Riyadh DC'])->assertCreated();
        $wh = \App\Models\Warehouse::first();
        $this->actingAs($this->seller, 'sanctum')->putJson("/api/products/{$id}", ['fulfilment' => 'armorhub'])->assertOk();
        $this->putJson("/api/products/{$id}/price-rules", ['rules' => [['target_type' => 'vehicle_part', 'target_id' => $this->hood->id, 'price' => 150, 'is_active' => true]]])->assertOk();
        app(\App\Services\Warehouse\WarehouseService::class)->move($wh->id, $id, 1, 'adjustment');           // exactly one unit on the shelf

        $other = User::factory()->create(['company_id' => $this->customer->company_id]);
        $other->assignRole('customer');
        $buy = function (User $who) use ($id) {
            $this->actingAs($who, 'sanctum')->postJson('/api/cart/items', ['product_id' => $id])->assertCreated();

            return $this->postJson('/api/checkout', ['shipping_address' => $this->address]);
        };
        $first = $buy($this->customer)->assertCreated()->json();                                                  // the unit is promised (not yet paid)
        $this->assertSame(0, (int) $this->getJson("/api/public/storefronts/main-store/products/{$id}")->json('product.units_left'));
        $this->assertFalse($this->getJson("/api/public/storefronts/main-store/products/{$id}")->json('product.in_stock'));
        $this->actingAs($other, 'sanctum')->postJson('/api/cart/items', ['product_id' => $id])->assertUnprocessable()->assertJsonValidationErrors('product_id');   // the second buyer is told, before paying

        // The first buyer changes their mind: the unit is free again.
        $this->actingAs($this->customer, 'sanctum')->postJson("/api/order-groups/{$first['id']}/cancel")->assertOk();
        $buy($other)->assertCreated();
    }

    public function test_warehouse_work_stays_inside_its_own_market_and_sellers_cannot_use_it(): void
    {
        $this->actingAs($this->seller, 'sanctum')->getJson('/api/warehouse/stock')->assertForbidden();
        $this->actingAs($this->staff, 'sanctum')->postJson('/api/warehouse/warehouses', ['name' => 'Riyadh DC'])->assertCreated();

        // Staff of another market see none of it.
        $other = \App\Models\Tenant::create(['name' => 'Other market', 'industry_id' => \App\Models\Industry::first()->id]);
        $co = Company::create(['tenant_id' => $other->id, 'name' => 'Other HQ', 'type' => 'partner', 'status' => 'active']);
        $foreign = User::factory()->create(['company_id' => $co->id]);
        $foreign->assignRole('warehouse-staff');
        $this->actingAs($foreign, 'sanctum')->getJson('/api/warehouse/warehouses')->assertOk()->assertJsonCount(0);
        $this->putJson('/api/warehouse/warehouses/'.\App\Models\Warehouse::first()->id, ['name' => 'Hijack'])->assertNotFound();
    }
}
