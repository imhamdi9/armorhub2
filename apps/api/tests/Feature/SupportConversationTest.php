<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** Help requests are conversations about an order, answered by a support agent who sees only their own market. */
class SupportConversationTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
    }

    private function agent(?int $companyId = null): User
    {
        $u = User::factory()->create(['company_id' => $companyId ?? $this->customer->company_id]);
        $u->assignRole('support-agent');

        return $u;
    }

    private function order(): Order
    {
        return Order::withoutGlobalScopes()->forceCreate(['company_id' => $this->customer->company_id, 'customer_id' => $this->customer->id, 'product_id' => $this->product->id, 'status' => 'paid',
            'product_amount' => 10, 'total_amount' => 10, 'currency' => 'USD']);
    }

    public function test_a_question_about_an_order_becomes_a_conversation(): void
    {
        $order = $this->order();
        $this->actingAs($this->customer, 'sanctum');
        $this->postJson('/api/support', ['subject' => 'Wrong item', 'body' => 'I got something else.', 'order_id' => 999999])->assertUnprocessable()->assertJsonValidationErrors('order_id');
        $other = User::factory()->create(['company_id' => $this->customer->company_id]);
        $other->assignRole('customer');
        $theirs = Order::withoutGlobalScopes()->forceCreate(['company_id' => $this->customer->company_id, 'customer_id' => $other->id, 'product_id' => $this->product->id, 'status' => 'paid', 'product_amount' => 1, 'total_amount' => 1, 'currency' => 'USD']);
        $this->postJson('/api/support', ['subject' => 'Not mine', 'body' => 'Someone else order.', 'order_id' => $theirs->id])->assertUnprocessable();      // only your own orders

        $t = $this->postJson('/api/support', ['subject' => 'Wrong item', 'body' => 'I got something else.', 'order_id' => $order->id])->assertCreated()->json();
        $this->assertSame($order->id, $t['order_id']);
        $this->assertCount(1, $t['messages']);

        $agent = $this->agent();
        $this->actingAs($agent, 'sanctum')->getJson('/api/admin/support')->assertOk()->assertJsonPath('0.order_id', $order->id)->assertJsonPath('0.messages.0.side', 'customer');
        $this->postJson("/api/admin/support/{$t['id']}/reply", ['reply' => 'Sorry! Can you send a photo?'])->assertOk();
        $this->actingAs($this->customer, 'sanctum')->postJson("/api/support/{$t['id']}/messages", ['body' => 'Photo is in the order chat.'])->assertOk()->assertJsonPath('status', 'open');
        $view = $this->getJson('/api/support')->json('0');
        $this->assertSame(['customer', 'staff', 'customer'], array_column($view['messages'], 'side'));
        $this->postJson("/api/support/{$t['id']}/close")->assertOk();
        $this->postJson("/api/support/{$t['id']}/messages", ['body' => 'One more thing.'])->assertOk()->assertJsonPath('status', 'open');   // writing again reopens it
    }

    public function test_a_support_agent_sees_only_their_own_market_and_nothing_about_money(): void
    {
        $this->actingAs($this->customer, 'sanctum')->postJson('/api/support', ['subject' => 'Help me', 'body' => 'Something is wrong.'])->assertCreated();

        $elsewhere = Tenant::create(['name' => 'Elsewhere']);
        $far = $this->agent(Company::create(['tenant_id' => $elsewhere->id, 'name' => 'Far Store', 'type' => 'supplier', 'status' => 'active'])->id);
        $this->actingAs($far, 'sanctum')->getJson('/api/admin/support')->assertOk()->assertJsonCount(0);          // another market's customers are not theirs
        $id = \App\Models\SupportTicket::value('id');
        $this->postJson("/api/admin/support/{$id}/reply", ['reply' => 'hello there'])->assertNotFound();

        $near = $this->agent();
        $this->actingAs($near, 'sanctum')->getJson('/api/admin/support')->assertOk()->assertJsonCount(1);
        foreach (['/api/admin/payouts', '/api/admin/payout-accounts', '/api/admin/settings', '/api/admin/outbox'] as $money) {
            $this->getJson($money)->assertForbidden();
        }
        $this->postJson('/api/admin/payouts', ['company_id' => 1, 'currency' => 'USD'])->assertForbidden();
    }

    public function test_a_return_request_carries_the_buyers_own_photos(): void
    {
        \Illuminate\Support\Facades\Storage::fake(config('platform.media_disk'));
        $order = $this->order();
        \App\Models\Shipment::create(['order_id' => $order->id, 'provider' => 'manual', 'status' => 'delivered', 'delivered_at' => now()->subDay()]);
        \App\Models\Payment::withoutGlobalScopes()->forceCreate(['order_id' => $order->id, 'company_id' => $order->company_id, 'amount' => 10, 'currency' => 'USD', 'status' => 'completed']);
        $this->product->update(['requires_shipping' => true]);
        $this->actingAs($this->customer, 'sanctum');

        $path = $this->post("/api/orders/{$order->id}/refund-photos", ['image' => \Illuminate\Http\UploadedFile::fake()->image('d.jpg', 300, 200)], ['Accept' => 'application/json'])->assertCreated()->json('path');
        $this->postJson("/api/orders/{$order->id}/refund-request", ['reason' => 'Arrived broken', 'photos' => ['/media/'.str_repeat('a', 36).'.jpg']])->assertUnprocessable();    // not theirs
        $this->postJson("/api/orders/{$order->id}/refund-request", ['reason' => 'Arrived broken', 'photos' => [$path]])->assertCreated();

        $this->assertSame([$path], $this->getJson("/api/orders/{$order->id}/refund-request")->json('request.photos'));
        $queue = $this->actingAs($this->agent(), 'sanctum')->getJson('/api/admin/refund-requests')->assertOk()->json();
        $this->assertSame([$path], $queue[0]['photos']);                                                         // the reviewer sees the evidence
    }

    public function test_support_can_give_goodwill_points_up_to_a_cap_inside_its_own_market(): void
    {
        $order = $this->order();
        $elsewhere = Tenant::create(['name' => 'Elsewhere']);
        $far = $this->agent(Company::create(['tenant_id' => $elsewhere->id, 'name' => 'Far Store', 'type' => 'supplier', 'status' => 'active'])->id);
        $this->actingAs($far, 'sanctum')->postJson("/api/admin/orders/{$order->id}/goodwill", ['points' => 50, 'why' => 'Late delivery'])->assertNotFound();

        $this->actingAs($this->agent(), 'sanctum');
        $before = app(\App\Services\Reputation::class)->points($this->customer->id);
        $this->postJson("/api/admin/orders/{$order->id}/goodwill", ['points' => 300, 'why' => 'Late delivery'])->assertOk()->assertJsonPath('points', 300);
        $this->assertSame($before + 300, app(\App\Services\Reputation::class)->points($this->customer->id));
        $this->postJson("/api/admin/orders/{$order->id}/goodwill", ['points' => 300, 'why' => 'Again'])->assertUnprocessable()->assertJsonValidationErrors('points');   // cap is 500 per order
        $this->postJson("/api/admin/orders/{$order->id}/goodwill", ['points' => 200, 'why' => 'Rest'])->assertOk();
        $this->assertNotNull($this->customer->notifications()->get()->first(fn ($n) => str_contains($n->data['title'] ?? '', 'points')));
        $this->actingAs($this->customer, 'sanctum')->postJson("/api/admin/orders/{$order->id}/goodwill", ['points' => 1, 'why' => 'me'])->assertForbidden();
    }

    public function test_the_customer_is_told_when_a_flagged_problem_was_looked_at(): void
    {
        $order = $this->order();
        $this->actingAs($this->customer, 'sanctum')->postJson("/api/orders/{$order->id}/messages", ['body' => 'The box was open', 'problem' => true])->assertCreated();
        $agent = $this->agent();
        $id = $this->actingAs($agent, 'sanctum')->getJson('/api/admin/order-problems')->assertOk()->assertJsonCount(1)->json('0.id');
        $this->postJson("/api/admin/order-problems/{$id}/handled", ['note' => 'We asked the seller to send a replacement.'])->assertOk();

        $note = $this->customer->notifications()->get()->first(fn ($n) => str_contains($n->data['title'] ?? '', 'looked at'))?->data;
        $this->assertNotNull($note);
        $this->assertStringContainsString("order #{$order->id}", $note['title']);
        $this->assertSame('We asked the seller to send a replacement.', $note['body']);
    }
}
