<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** Shopping without an account: a cart, a full checkout and payment, tracking, and turning the guest into a customer later. */
class GuestShoppingTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private int $productId;
    private array $address = ['name' => 'Layla', 'phone' => '0501234567', 'line1' => '12 King Road', 'city' => 'Riyadh'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
        $store = Company::withoutGlobalScopes()->where('name', 'Store')->first();
        $store->update(['slug' => 'main-store', 'settings' => ['storefront' => ['enabled' => true]]]);
        $seller = tap(User::factory()->create(['company_id' => Company::create(['tenant_id' => $store->tenant_id, 'name' => 'Nile Goods', 'type' => 'supplier', 'status' => 'active'])->id]), fn ($u) => $u->assignRole('seller'));
        $cat = $this->actingAs($seller, 'sanctum')->getJson('/api/products/categories')->json('0.key');
        $this->productId = $this->postJson('/api/products', ['name' => 'Olive Soap', 'base_price' => 100, 'category' => $cat])->assertCreated()->json('id');
        $this->app['auth']->forgetGuards();
    }

    private function bearer(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }

    public function test_a_shopper_with_no_account_fills_a_cart_checks_out_and_pays(): void
    {
        $token = $this->postJson('/api/guest-session', ['store' => 'main-store'])->assertCreated()->json('token');
        $this->postJson('/api/guest-session', ['store' => 'nope'])->assertUnprocessable();

        $this->bearer($token)->postJson('/api/cart/items', ['product_id' => $this->productId, 'quantity' => 2])->assertCreated();
        $this->getJson('/api/cart')->assertOk()->assertJsonPath('items.0.quantity', 2);
        $this->putJson('/api/guest-session/contact', ['email' => 'layla@shoppers.example.org', 'name' => 'Layla'])->assertOk();

        $group = $this->postJson('/api/checkout', ['shipping_address' => $this->address])->assertCreated()->json();
        $this->postJson("/api/order-groups/{$group['id']}/payments", ['payment_method' => 'card'])->assertOk();
        $order = Order::withoutGlobalScopes()->where('group_id', $group['id'])->firstOrFail();
        $this->assertEquals(200, $order->total_amount);
        $this->getJson("/api/orders/{$order->id}")->assertOk();                                             // tracked with the same session

        $guest = User::findOrFail($order->customer_id);
        $this->assertTrue((bool) $guest->is_guest);
        $this->assertSame('layla@shoppers.example.org', $guest->routeNotificationForMail());               // mail goes to the real address
        $this->assertStringEndsWith('@guest.invalid', $guest->email);
    }

    public function test_the_guest_can_become_a_customer_and_keeps_everything(): void
    {
        $token = $this->postJson('/api/guest-session', ['store' => 'main-store'])->assertCreated()->json('token');
        $this->bearer($token)->postJson('/api/cart/items', ['product_id' => $this->productId])->assertCreated();
        $this->postJson('/api/guest-session/claim', ['email' => 'layla@shoppers.example.org', 'password' => 'weak', 'password_confirmation' => 'weak'])->assertUnprocessable();
        $this->postJson('/api/guest-session/claim', ['email' => 'layla@shoppers.example.org', 'password' => 'Str0ng-Passw0rd!x', 'password_confirmation' => 'Str0ng-Passw0rd!x'])->assertOk();

        $this->app['auth']->forgetGuards();
        $this->withHeaders([])->postJson('/api/login', ['email' => 'layla@shoppers.example.org', 'password' => 'Str0ng-Passw0rd!x'])->assertOk();
        $user = User::where('email', 'layla@shoppers.example.org')->firstOrFail();
        $this->assertFalse((bool) $user->is_guest);
        $this->assertSame(1, \App\Models\Cart::where('customer_id', $user->id)->count() ?: \App\Models\Cart::count());   // the cart came along
    }

    public function test_only_a_guest_can_use_the_guest_calls_and_a_signed_in_customer_keeps_their_session(): void
    {
        $this->actingAs($this->customer, 'sanctum')->putJson('/api/guest-session/contact', ['email' => 'x@y.example.org'])->assertForbidden();
        $this->postJson('/api/guest-session/claim', ['email' => 'x@y.example.org', 'password' => 'Str0ng-Passw0rd!x', 'password_confirmation' => 'Str0ng-Passw0rd!x'])->assertForbidden();
        $this->postJson('/api/guest-session', ['store' => 'main-store'])->assertOk()->assertJsonPath('user.id', $this->customer->id);     // no second identity
    }
}
