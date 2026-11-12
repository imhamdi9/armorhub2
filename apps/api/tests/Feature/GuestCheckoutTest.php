<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Notifications\GuestOrderUpdate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Buying a seller's goods with no account: what the buyer can do, what they can never see, and how an account may be added later. */
class GuestCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->artisan('platform:demo-egypt')->assertSuccessful();
    }

    private function body(array $extra = []): array
    {
        $challenge = $this->getJson('/api/signup-challenge')->assertOk()->json();
        $this->travel(5)->seconds();

        return $extra + [
            'store' => 'beit-masr', 'product_id' => Product::where('slug', 'eg-lantern')->value('id'), 'name' => 'Salma Guest', 'email' => 'Salma.Guest@Example.org',
            'phone' => '01012345678', 'address' => ['line1' => '12 Nile Corniche', 'city' => 'Giza'], 'signup_token' => $challenge['token'],
        ];
    }

    private function order(array $extra = []): array
    {
        return $this->postJson('/api/guest-orders', $this->body($extra))->assertCreated()->json();
    }

    public function test_a_guest_orders_goods_pays_and_follows_the_order_with_a_secret_link(): void
    {
        Notification::fake();
        $o = $this->order();

        $this->assertSame(48, strlen($o['token']));
        $this->assertSame('awaiting_payment', $o['status']);
        $this->assertEquals(650, $o['total']);                                         // the lantern's price
        $this->assertSame('EG', $o['shipping_address']['country']);                   // the store's country, not chosen by the buyer
        $this->assertStringStartsWith('+20', $o['shipping_address']['phone']);        // the Egyptian mobile number was normalised
        $order = Order::withoutGlobalScopes()->find($o['id']);
        $this->assertNull($order->customer_id);
        $this->assertSame('salma.guest@example.org', $order->guest_email);

        $this->postJson("/api/guest-orders/{$o['token']}/pay")->assertOk()->assertJsonPath('status', 'paid');
        $this->postJson("/api/guest-orders/{$o['token']}/pay")->assertStatus(422);    // not twice
        $this->getJson("/api/guest-orders/{$o['token']}")->assertOk()->assertJsonPath('can_pay', false)->assertJsonPath('product.seller', 'Khan El-Khalili Crafts');

        Notification::assertSentOnDemand(GuestOrderUpdate::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'salma.guest@example.org' && $n->token === $o['token']);
    }

    public function test_the_seller_sees_what_to_ship_and_the_money_is_held(): void
    {
        $o = $this->order();
        $this->postJson("/api/guest-orders/{$o['token']}/pay")->assertOk();

        $this->actingAs(User::where('email', 'seller.khan@demo.eg')->first(), 'sanctum');
        $sale = $this->getJson("/api/seller/orders/{$o['id']}")->assertOk()->json();
        $this->assertSame('Salma Guest', $sale['customer_name']);
        $this->assertSame('12 Nile Corniche', $sale['shipping_address']['line1']);
        $this->assertStringNotContainsString('guest_token', json_encode($sale));
        $this->assertStringNotContainsString($o['token'], json_encode($sale));         // staff cannot use someone's link
        $this->assertGreaterThan(0, \App\Models\SellerLedgerEntry::where('order_id', $o['id'])->count());
    }

    public function test_only_the_holder_of_the_link_can_see_or_change_the_order(): void
    {
        $o = $this->order();
        $this->getJson('/api/guest-orders/'.str_repeat('a', 48))->assertNotFound();
        $this->getJson('/api/guest-orders/short')->assertNotFound();
        $this->postJson('/api/guest-orders/'.str_repeat('a', 48).'/pay')->assertNotFound();

        $this->actingAs(User::where('email', 'nour.fawzy@demo.eg')->first(), 'sanctum');
        $this->assertContains($this->getJson("/api/orders/{$o['id']}")->status(), [403, 404]);   // another customer cannot open it through the dashboard either

        $this->postJson("/api/guest-orders/{$o['token']}/cancel")->assertOk()->assertJsonPath('status', 'cancelled');
        $this->postJson("/api/guest-orders/{$o['token']}/pay")->assertStatus(422);
    }

    public function test_only_available_goods_in_this_store_can_be_bought_without_an_account(): void
    {
        $service = Product::where('slug', 'eg-cleaning')->value('id');                // a service needs quotes, so it needs an account
        $this->postJson('/api/guest-orders', $this->body(['product_id' => $service]))->assertStatus(422)->assertJsonValidationErrors('product_id');
        $other = Product::where('slug', 'eg-glass')->value('id');                    // a Cairo Devices product is not sold at Beit Masr
        $this->postJson('/api/guest-orders', $this->body(['product_id' => $other]))->assertStatus(422);
        $this->postJson('/api/guest-orders', $this->body(['store' => 'no-such-store']))->assertNotFound();
        Product::where('slug', 'eg-lantern')->update(['is_active' => false]);
        $this->postJson('/api/guest-orders', $this->body())->assertStatus(422);
    }

    public function test_the_form_needs_real_contact_details_and_a_human(): void
    {
        $this->postJson('/api/guest-orders', $this->body(['phone' => '123']))->assertStatus(422)->assertJsonValidationErrors('phone');
        $this->postJson('/api/guest-orders', $this->body(['email' => 'not-an-email']))->assertStatus(422)->assertJsonValidationErrors('email');
        $this->postJson('/api/guest-orders', $this->body(['address' => ['city' => 'Giza']]))->assertStatus(422)->assertJsonValidationErrors('address.line1');
        $this->postJson('/api/guest-orders', array_diff_key($this->body(), ['signup_token' => 1]))->assertStatus(422);        // no human-check token
        $this->postJson('/api/guest-orders', $this->body(['website' => 'http://spam.example']))->assertStatus(422);           // the honeypot was filled
    }

    public function test_orders_can_be_paused_and_the_flood_is_limited(): void
    {
        app(\App\Support\Settings\Settings::class)->set('platform.orders_enabled', false);
        $this->postJson('/api/guest-orders', $this->body())->assertStatus(503);
        app(\App\Support\Settings\Settings::class)->set('platform.orders_enabled', true);

        $codes = [];
        foreach (range(1, 8) as $i) {
            $codes[] = $this->postJson('/api/guest-orders', $this->body())->status();
        }
        $this->assertContains(429, $codes);
    }

    public function test_an_account_with_the_same_verified_email_claims_the_guest_orders(): void
    {
        $o = $this->order();
        $email = 'salma.guest@example.org';

        $store = \App\Models\Company::withoutGlobalScopes()->where('slug', 'beit-masr')->first();
        $user = User::factory()->create(['email' => $email, 'email_verified_at' => null, 'company_id' => $store->id]);
        $user->assignRole('customer');
        $this->actingAs($user, 'sanctum')->getJson('/api/orders')->assertOk()->assertJsonCount(0, 'data');   // not verified: nothing is handed over
        $this->assertNull(Order::withoutGlobalScopes()->find($o['id'])->customer_id);

        $user->forceFill(['email_verified_at' => now()])->save();
        $this->getJson('/api/orders')->assertOk()->assertJsonCount(1, 'data');
        $claimed = Order::withoutGlobalScopes()->find($o['id']);
        $this->assertSame($user->id, $claimed->customer_id);
        $this->assertNull($claimed->guest_token);                                                              // the old link stops working
        $this->getJson("/api/guest-orders/{$o['token']}")->assertNotFound();
    }

    public function test_signed_in_ordering_still_works_as_before(): void
    {
        $this->actingAs(User::where('email', 'khaled.abdelrahman@demo.eg')->first(), 'sanctum');
        $lantern = Product::where('slug', 'eg-lantern')->first();
        $rule = $lantern->priceRules()->first();
        $this->postJson('/api/orders', ['product_id' => $lantern->id, 'target_type' => $rule->target_type, 'targets' => [$rule->target_id],
            'shipping_address' => ['name' => 'Khaled', 'phone' => '01012345678', 'line1' => '1 Street', 'city' => 'Giza', 'country' => 'EG']])->assertCreated();
    }
}
