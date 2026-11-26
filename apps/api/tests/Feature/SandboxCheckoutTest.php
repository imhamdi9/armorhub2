<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\SandboxCharge;
use App\Models\User;
use App\Services\Ledger\Accounts;
use App\Services\Ledger\Ledger;
use App\Support\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** The hosted test checkout: redirect, decline, retry and confirmation, for a whole cart purchase. */
class SandboxCheckoutTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private array $address = ['name' => 'Layla', 'phone' => '0501234567', 'line1' => '12 King Road', 'city' => 'Riyadh'];
    private array $good = ['method' => 'card', 'name' => 'Layla', 'number' => '4242 4242 4242 4242', 'expiry' => '12/39', 'cvc' => '123'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
        app(Settings::class)->set('payments.active_provider', 'sandbox');
        $store = Company::withoutGlobalScopes()->where('name', 'Store')->first();
        $store->update(['slug' => 'main-store', 'settings' => ['storefront' => ['enabled' => true]]]);
        $co = Company::create(['tenant_id' => $store->tenant_id, 'name' => 'Gulf Parts', 'type' => 'supplier', 'status' => 'active']);
        $seller = User::factory()->create(['email' => 'a@sellers.example.org', 'company_id' => $co->id]);
        $seller->assignRole('seller');

        $id = $this->actingAs($seller, 'sanctum')->postJson('/api/products', ['name' => 'Brake pads', 'slug' => 'brake-pads-x', 'base_price' => 100, 'category' => 'spare-parts'])->assertCreated()->json('id');
        $this->putJson("/api/products/{$id}/price-rules", ['rules' => [['target_type' => 'vehicle_part', 'target_id' => $this->hood->id, 'price' => 100, 'is_active' => true]]])->assertOk();
        $this->actingAs($this->customer, 'sanctum')->postJson('/api/cart/items', ['product_id' => $id])->assertCreated();
    }

    private function checkout(): array
    {
        $group = $this->postJson('/api/checkout', ['shipping_address' => $this->address])->assertCreated()->json();
        $res = $this->postJson("/api/order-groups/{$group['id']}/payments", ['payment_method' => 'card'])->assertStatus(202)->json();
        $this->assertStringContainsString('/pay/sandbox/SBX-', $res['checkout_url']);

        return [$group, basename($res['checkout_url'])];
    }

    public function test_a_card_is_declined_then_retried_and_the_purchase_is_paid(): void
    {
        [$group, $ref] = $this->checkout();
        $this->assertSame('awaiting_payment', OrderGroup::find($group['id'])->status);

        $page = $this->getJson("/api/public/sandbox/{$ref}")->assertOk()->json();
        $this->assertEqualsWithDelta($group['payable_amount'], $page['amount'], 0.001);
        $this->assertStringEndsWith("/dashboard/purchases/{$group['id']}", $page['return_url']);

        $this->postJson("/api/public/sandbox/{$ref}/pay", ['number' => '4000000000000002'] + $this->good)->assertStatus(422)->assertJsonPath('status', 'declined')->assertJsonPath('message', 'Your card was declined.');
        $this->postJson("/api/public/sandbox/{$ref}/pay", ['number' => '4111111111111111'] + $this->good)->assertStatus(422);           // not a test card
        $this->postJson("/api/public/sandbox/{$ref}/pay", ['expiry' => '01/20'] + $this->good)->assertStatus(422)->assertJsonPath('message', 'Your card has expired.');
        $this->assertSame('awaiting_payment', OrderGroup::find($group['id'])->status);                                  // nothing was charged

        $this->postJson("/api/public/sandbox/{$ref}/pay", $this->good)->assertOk()->assertJsonPath('status', 'paid');
        $this->assertSame('paid', OrderGroup::find($group['id'])->status);
        $this->assertEqualsWithDelta($group['payable_amount'], app(Ledger::class)->balance(Accounts::GATEWAY, $group['currency']), 0.001);
        $this->postJson("/api/public/sandbox/{$ref}/pay", $this->good)->assertOk()->assertJsonPath('status', 'paid');   // a second tap pays nothing twice
        $this->assertSame(1, \App\Models\Payment::withoutGlobalScopes()->whereIn('order_id', Order::withoutGlobalScopes()->where('group_id', $group['id'])->pluck('id'))->count());
    }

    public function test_a_wallet_code_pays_and_cancelling_leaves_the_purchase_waiting(): void
    {
        [$group, $ref] = $this->checkout();
        $this->postJson("/api/public/sandbox/{$ref}/pay", ['method' => 'wallet', 'phone' => '01012345678', 'code' => '000000'])->assertStatus(422);
        $this->postJson("/api/public/sandbox/{$ref}/cancel")->assertOk()->assertJsonPath('status', 'cancelled');
        $this->postJson("/api/public/sandbox/{$ref}/pay", ['method' => 'wallet', 'phone' => '01012345678', 'code' => '123456'])->assertOk()->assertJsonPath('status', 'cancelled');
        $this->assertSame('awaiting_payment', OrderGroup::find($group['id'])->status);

        $second = $this->postJson("/api/order-groups/{$group['id']}/payments", ['payment_method' => 'wallet'])->assertStatus(202)->json('checkout_url');    // a new try, a new page
        $this->postJson('/api/public/sandbox/'.basename($second).'/pay', ['method' => 'wallet', 'phone' => '01012345678', 'code' => '123456'])->assertOk()->assertJsonPath('status', 'paid');
        $this->assertSame('paid', OrderGroup::find($group['id'])->status);
    }

    public function test_a_refund_is_sent_back_through_the_provider_that_took_the_money(): void
    {
        [$group, $ref] = $this->checkout();
        $this->postJson("/api/public/sandbox/{$ref}/pay", $this->good)->assertOk();
        $order = Order::withoutGlobalScopes()->where('group_id', $group['id'])->firstOrFail();

        $this->actingAs($this->admin, 'sanctum')->postJson("/api/admin/orders/{$order->id}/refund-send")->assertUnprocessable();         // nothing owed yet
        $this->postJson("/api/admin/orders/{$order->id}/refund", ['reason' => 'Test refund'])->assertOk()->assertJsonPath('refund_status', 'pending');
        $sent = $this->postJson("/api/admin/orders/{$order->id}/refund-send")->assertOk()->assertJsonPath('refund_status', 'refunded')->json();
        $this->assertStringStartsWith('SBX-RF-', $sent['reference']);
        $this->assertEqualsWithDelta(0, app(Ledger::class)->balance(Accounts::GATEWAY, $group['currency']), 0.001);                     // the money left again
        $this->postJson("/api/admin/orders/{$order->id}/refund-send")->assertUnprocessable();                                            // once
    }

    public function test_the_page_is_closed_when_the_sandbox_is_not_the_provider_in_production(): void
    {
        [, $ref] = $this->checkout();
        $this->assertSame(1, SandboxCharge::count());
        $this->getJson('/api/public/sandbox/SBX-NOSUCHCHARGE')->assertNotFound();
        $this->getJson('/api/public/sandbox/not-a-ref')->assertNotFound();
    }
}
