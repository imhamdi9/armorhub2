<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\Payment;
use App\Models\SandboxCharge;
use App\Models\User;
use App\Services\Payment\PaymentManager;
use App\Support\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** Pressing pay twice, or a request sent again after a dropped connection, pays once. */
class DoublePaymentTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private array $address = ['name' => 'Layla', 'phone' => '0501234567', 'line1' => '12 King Road', 'city' => 'Riyadh'];

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

    private function group(): array
    {
        return $this->postJson('/api/checkout', ['shipping_address' => $this->address])->assertCreated()->json();
    }

    private function pay(int $group, string $method = 'card'): string
    {
        return $this->postJson("/api/order-groups/{$group}/payments", ['payment_method' => $method])->assertStatus(202)->json('checkout_url');
    }

    public function test_asking_to_pay_twice_opens_one_payment_page(): void
    {
        $group = $this->group();
        $this->assertSame($this->pay($group['id']), $this->pay($group['id']));       // a second tab, or a second tap
        $this->assertSame(1, SandboxCharge::count());
    }

    public function test_a_cancelled_payment_page_is_not_offered_again(): void
    {
        $group = $this->group();
        $first = $this->pay($group['id']);
        $this->postJson('/api/public/sandbox/'.basename($first).'/cancel')->assertOk();
        $this->assertNotSame($first, $this->pay($group['id']));
    }

    public function test_a_single_order_keeps_one_pending_payment_however_often_pay_is_pressed(): void
    {
        $order = $this->order(false);
        Order::withoutGlobalScopes()->whereKey($order)->update(['status' => 'awaiting_payment']);
        $amount = Order::withoutGlobalScopes()->findOrFail($order)->payable_amount;
        $urls = [];
        foreach ([1, 2] as $_) {
            $urls[] = $this->actingAs($this->customer, 'sanctum')->postJson("/api/orders/{$order}/payments", ['amount' => $amount, 'payment_method' => 'card'])->assertStatus(202)->json('checkout_url');
        }
        $this->assertSame($urls[0], $urls[1]);
        $this->assertSame(1, Payment::withoutGlobalScopes()->where('order_id', $order)->count());
    }

    public function test_a_second_payment_while_the_first_is_being_started_is_refused(): void
    {
        $group = $this->group();
        $lock = Cache::lock(PaymentManager::cacheKey(OrderGroup::find($group['id'])->paymentId()).':lock', 30);
        $this->assertTrue($lock->get());
        $this->postJson("/api/order-groups/{$group['id']}/payments", ['payment_method' => 'card'])->assertStatus(409);
        $this->assertSame(0, SandboxCharge::count());
        $lock->release();
        $this->pay($group['id']);
    }

    public function test_a_checkout_sent_again_with_the_same_key_makes_one_purchase(): void
    {
        $send = fn () => $this->withHeader('Idempotency-Key', 'checkout-1')->postJson('/api/checkout', ['shipping_address' => $this->address])->assertCreated();
        $first = $send()->json('id');
        $again = $send();
        $again->assertHeader('Idempotent-Replayed', 'true');
        $this->assertSame($first, $again->json('id'));
        $this->assertSame(1, OrderGroup::count());
    }

    public function test_a_guest_order_sent_again_makes_one_order_even_with_a_fresh_human_check(): void
    {
        $this->app['auth']->forgetGuards();
        $product = \App\Models\Product::withoutGlobalScopes()->where('slug', 'brake-pads-x')->value('id');
        $send = fn (string $humanCheck) => $this->withHeader('Idempotency-Key', 'guest-1')->postJson('/api/guest-orders', [
            'store' => 'main-store', 'product_id' => $product, 'name' => 'Layla', 'email' => 'layla@shoppers.example.org', 'phone' => '0501234567',
            'address' => ['line1' => '12 King Road', 'city' => 'Riyadh'], 'signup_token' => $humanCheck,
        ])->assertCreated()->json('token');

        $token = $this->getJson('/api/signup-challenge')->assertOk()->json('token');
        $this->travel(10)->seconds();

        // The second attempt carries another (here: useless) human check, as a page that refreshed it would.
        $this->assertSame($send($token), $send('refreshed'));
        $this->assertSame(1, Order::withoutGlobalScopes()->whereNotNull('guest_token')->count());
    }
}
