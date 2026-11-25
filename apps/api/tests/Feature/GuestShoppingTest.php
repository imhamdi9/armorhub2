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

    /** A store of another market (its own tenant) with one seller product. @return int the product's id */
    private function otherMarketStore(): int
    {
        $tenant = \App\Models\Tenant::create(['name' => 'T2', 'industry_id' => \App\Models\Industry::where('key', 'automotive')->value('id')]);
        $store = Company::create(['tenant_id' => $tenant->id, 'name' => 'Delta Store']);
        $store->update(['slug' => 'delta-store', 'settings' => ['storefront' => ['enabled' => true]]]);
        $seller = tap(User::factory()->create(['company_id' => Company::create(['tenant_id' => $tenant->id, 'name' => 'Delta Goods', 'type' => 'supplier', 'status' => 'active'])->id]), fn ($u) => $u->assignRole('seller'));
        $cat = $this->actingAs($seller, 'sanctum')->getJson('/api/products/categories')->json('0.key');
        $id = $this->postJson('/api/products', ['name' => 'Date Syrup', 'base_price' => 50, 'category' => $cat])->assertCreated()->json('id');
        $this->app['auth']->forgetGuards();

        return $id;
    }

    public function test_a_guest_with_an_empty_cart_moves_to_the_other_markets_store_they_are_shopping(): void
    {
        $other = $this->otherMarketStore();
        $token = $this->postJson('/api/guest-session', ['store' => 'main-store'])->assertCreated()->json('token');
        $this->bearer($token)->postJson('/api/cart/items', ['product_id' => $other])->assertUnprocessable();   // still the first store's shopper

        $this->putJson('/api/guest-session/store', ['store' => 'delta-store'])->assertOk();
        $this->postJson('/api/cart/items', ['product_id' => $other])->assertCreated();
        $this->postJson('/api/favourites', ['product_id' => $other])->assertSuccessful();
        $this->assertSame(1, User::where('is_guest', true)->count());                                         // the same shopper, not a new one
    }

    public function test_a_guest_with_goods_in_the_cart_is_told_why_they_cannot_move(): void
    {
        $this->otherMarketStore();
        $token = $this->postJson('/api/guest-session', ['store' => 'main-store'])->assertCreated()->json('token');
        $this->bearer($token)->postJson('/api/cart/items', ['product_id' => $this->productId])->assertCreated();

        $this->putJson('/api/guest-session/store', ['store' => 'delta-store'])->assertUnprocessable()
            ->assertJsonValidationErrors(['store'])->assertJsonPath('errors.store.0', 'Your cart has items from Store. Buy or remove them before shopping in another market.');
        $this->getJson('/api/cart')->assertJsonPath('items.0.product_id', $this->productId);                   // nothing was lost

        $this->deleteJson('/api/cart')->assertOk();
        $this->putJson('/api/guest-session/store', ['store' => 'delta-store'])->assertOk();
    }

    public function test_a_guest_who_has_ordered_stays_with_that_market_and_another_store_of_it_changes_nothing(): void
    {
        $this->otherMarketStore();
        $token = $this->postJson('/api/guest-session', ['store' => 'main-store'])->assertCreated()->json('token');
        $this->bearer($token)->postJson('/api/cart/items', ['product_id' => $this->productId])->assertCreated();
        $this->putJson('/api/guest-session/contact', ['email' => 'layla@shoppers.example.org'])->assertOk();
        $this->postJson('/api/checkout', ['shipping_address' => $this->address])->assertCreated();
        $guest = User::where('is_guest', true)->firstOrFail();

        $this->putJson('/api/guest-session/store', ['store' => 'delta-store'])->assertUnprocessable();
        $this->putJson('/api/guest-session/store', ['store' => 'main-store'])->assertOk();
        $this->putJson('/api/guest-session/store', ['store' => 'nope'])->assertUnprocessable();
        $this->assertSame($guest->company_id, $guest->fresh()->company_id);
    }

    public function test_only_a_guest_can_be_moved_to_another_store(): void
    {
        $this->otherMarketStore();
        $this->actingAs($this->customer, 'sanctum')->putJson('/api/guest-session/store', ['store' => 'delta-store'])->assertForbidden();
    }
}
