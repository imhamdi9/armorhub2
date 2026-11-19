<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** Talking about an order on the platform, and flagging a problem for ArmorHub. */
class OrderMessagesTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private User $seller;
    private Order $order;
    private array $address = ['name' => 'Layla', 'phone' => '0501234567', 'line1' => '12 King Road', 'city' => 'Riyadh'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
        $store = Company::withoutGlobalScopes()->where('name', 'Store')->first();
        $store->update(['slug' => 'main-store', 'settings' => ['storefront' => ['enabled' => true]]]);
        $co = Company::create(['tenant_id' => $store->tenant_id, 'name' => 'Gulf Parts', 'type' => 'supplier', 'status' => 'active']);
        $this->seller = User::factory()->create(['email' => 'a@sellers.example.org', 'company_id' => $co->id]);
        $this->seller->assignRole('seller');

        $id = $this->actingAs($this->seller, 'sanctum')->postJson('/api/products', ['name' => 'Brake pads', 'slug' => 'brake-pads-x', 'base_price' => 100, 'category' => 'spare-parts'])->assertCreated()->json('id');
        $this->putJson("/api/products/{$id}/price-rules", ['rules' => [['target_type' => 'vehicle_part', 'target_id' => $this->hood->id, 'price' => 100, 'is_active' => true]]])->assertOk();
        $this->actingAs($this->customer, 'sanctum')->postJson('/api/cart/items', ['product_id' => $id])->assertCreated();
        $group = $this->postJson('/api/checkout', ['shipping_address' => $this->address])->assertCreated()->json();
        $this->postJson("/api/order-groups/{$group['id']}/payments", [])->assertOk();
        $this->order = Order::withoutGlobalScopes()->where('group_id', $group['id'])->firstOrFail();
    }

    public function test_the_customer_and_the_seller_talk_and_each_is_told(): void
    {
        $sellerBefore = $this->seller->notifications()->count();
        $customerBefore = $this->customer->notifications()->count();
        $this->actingAs($this->customer, 'sanctum')->postJson("/api/orders/{$this->order->id}/messages", ['body' => 'When will it ship?'])->assertCreated()->assertJsonPath('side', 'customer')->assertJsonPath('mine', true);
        $this->assertSame($sellerBefore + 1, $this->seller->notifications()->count());                  // the seller was told

        $this->actingAs($this->seller, 'sanctum')->getJson("/api/orders/{$this->order->id}/messages")->assertOk()->assertJsonCount(1)->assertJsonPath('0.mine', false);
        $this->postJson("/api/orders/{$this->order->id}/messages", ['body' => 'Tomorrow morning.'])->assertCreated()->assertJsonPath('side', 'seller');
        $this->assertSame($customerBefore + 1, $this->customer->notifications()->count());              // so was the customer

        $this->actingAs($this->customer, 'sanctum')->getJson("/api/orders/{$this->order->id}/messages")->assertOk()->assertJsonCount(2);
    }

    public function test_contact_details_are_refused_and_only_the_people_of_the_order_may_write(): void
    {
        $this->actingAs($this->customer, 'sanctum');
        foreach (['Call me on 0501234567', 'mail me a@b.com', 'see https://example.org/x'] as $text) {
            $this->postJson("/api/orders/{$this->order->id}/messages", ['body' => $text])->assertUnprocessable();
        }
        $this->postJson("/api/orders/{$this->order->id}/messages", ['body' => ''])->assertUnprocessable();

        $other = User::factory()->create(['company_id' => $this->customer->company_id]);
        $other->assignRole('customer');
        $this->actingAs($other, 'sanctum')->getJson("/api/orders/{$this->order->id}/messages")->assertNotFound();
        $this->postJson("/api/orders/{$this->order->id}/messages", ['body' => 'hello there'])->assertNotFound();

        $rival = User::factory()->create(['company_id' => Company::create(['tenant_id' => Company::withoutGlobalScopes()->where('name', 'Store')->value('tenant_id'), 'name' => 'Rival Parts', 'type' => 'supplier', 'status' => 'active'])->id]);
        $rival->assignRole('seller');
        $this->actingAs($rival, 'sanctum')->getJson("/api/orders/{$this->order->id}/messages")->assertNotFound();      // another seller cannot read it
    }

    public function test_a_problem_reaches_administrators_and_is_marked_handled(): void
    {
        $this->actingAs($this->customer, 'sanctum')->postJson("/api/orders/{$this->order->id}/messages", ['body' => 'The box was open', 'problem' => true])->assertCreated()->assertJsonPath('kind', 'problem');
        $this->assertGreaterThan(0, $this->admin->notifications()->count());

        $this->actingAs($this->seller, 'sanctum')->postJson("/api/orders/{$this->order->id}/messages", ['body' => 'Sorry about that', 'problem' => true])->assertCreated()->assertJsonPath('kind', 'message');   // only customers flag problems
        $this->getJson('/api/admin/order-problems')->assertForbidden();

        $this->actingAs($this->admin, 'sanctum');
        $id = $this->getJson('/api/admin/order-problems')->assertOk()->assertJsonCount(1)->json('0.id');
        $this->postJson("/api/admin/order-messages-nope", [])->assertNotFound();
        $this->postJson("/api/admin/order-problems/{$id}/handled")->assertOk();
        $this->getJson('/api/admin/order-problems')->assertOk()->assertJsonCount(0);
        $this->postJson("/api/orders/{$this->order->id}/messages", ['body' => 'We are looking into it.'])->assertCreated()->assertJsonPath('side', 'support');
    }
}
