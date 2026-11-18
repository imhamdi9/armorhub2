<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Order;
use App\Models\User;
use App\Services\ShippingDeadlines;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** A paid order that a seller does not ship is chased, then cancelled and refunded. */
class ShippingDeadlinesTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private User $seller;
    private int $productId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
        $co = Company::create(['tenant_id' => $this->customer->company->tenant_id, 'name' => 'Gulf Parts', 'type' => 'supplier', 'status' => 'active']);
        $this->seller = User::factory()->create(['email' => 'a@sellers.example.org', 'company_id' => $co->id]);
        $this->seller->assignRole('seller');
        $this->productId = $this->actingAs($this->seller, 'sanctum')->postJson('/api/products', ['name' => 'Brake pads', 'slug' => 'bp-dl', 'base_price' => 100, 'category' => 'spare-parts', 'requires_shipping' => true])->json('id');
        $this->putJson("/api/products/{$this->productId}/price-rules", ['rules' => [['target_type' => 'vehicle_part', 'target_id' => $this->hood->id, 'price' => 100, 'is_active' => true]]])->assertOk();
    }

    private function paidOrder(): Order
    {
        $this->actingAs($this->customer, 'sanctum')->postJson('/api/cart/items', ['product_id' => $this->productId])->assertCreated();
        $g = $this->postJson('/api/checkout', ['shipping_address' => ['name' => 'L', 'phone' => '0501234567', 'line1' => 'x', 'city' => 'Riyadh']])->assertCreated()->json();
        $this->postJson("/api/order-groups/{$g['id']}/payments", [])->assertOk();

        return Order::withoutGlobalScopes()->where('group_id', $g['id'])->firstOrFail();
    }

    public function test_an_unshipped_order_is_reminded_then_cancelled_and_refunded(): void
    {
        $order = $this->paidOrder();
        $run = fn () => app(ShippingDeadlines::class)->run();

        $this->assertSame(['reminded' => 0, 'cancelled' => 0], $run());                                          // just paid
        $this->travel(4)->days();
        $this->assertSame(['reminded' => 1, 'cancelled' => 0], $run());
        $this->assertTrue($this->seller->notifications()->get()->contains(fn ($n) => ($n->data['title'] ?? '') === 'Ship this order now'));
        $this->assertSame(['reminded' => 0, 'cancelled' => 0], $run());                                          // one reminder only

        $this->travel(4)->days();
        $this->assertSame(['reminded' => 0, 'cancelled' => 1], $run());
        $fresh = Order::withoutGlobalScopes()->find($order->id);
        $this->assertSame('pending', $fresh->refund_status);
        $this->assertSame('The seller did not ship in time.', $fresh->refund_reason);
        $this->assertTrue($this->customer->notifications()->get()->contains(fn ($n) => str_contains($n->data['title'] ?? '', 'refunded')));
        $this->assertTrue($this->seller->notifications()->get()->contains(fn ($n) => str_contains($n->data['title'] ?? '', 'cancelled')));
        $this->assertSame(['reminded' => 0, 'cancelled' => 0], $run());                                          // done once
    }

    public function test_a_shipped_order_is_left_alone_and_the_deadlines_can_be_switched_off(): void
    {
        $order = $this->paidOrder();
        $this->actingAs($this->seller, 'sanctum')->postJson("/api/orders/{$order->id}/shipments", ['provider' => 'manual', 'carrier' => 'SMSA', 'tracking_number' => 'T1'])->assertCreated();
        $this->travel(20)->days();
        $this->assertSame(['reminded' => 0, 'cancelled' => 0], app(ShippingDeadlines::class)->run());

        $second = $this->paidOrder();
        app(\App\Support\Settings\Settings::class)->set('orders.ship_remind_days', 0);
        app(\App\Support\Settings\Settings::class)->set('orders.ship_cancel_days', 0);
        $this->travel(30)->days();
        $this->assertSame(['reminded' => 0, 'cancelled' => 0], app(ShippingDeadlines::class)->run());
        $this->assertNull(Order::withoutGlobalScopes()->find($second->id)->refund_status);
    }
}
