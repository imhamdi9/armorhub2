<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Company;
use App\Models\LoyaltyEntry;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\User;
use App\Services\CartReminders;
use App\Services\Loyalty;
use App\Support\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** Points for goods that arrived, spending them, referrals, and the reminder for a forgotten cart. */
class RetentionTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private User $seller;
    private int $productId;
    private array $address = ['name' => 'Layla', 'phone' => '0501234567', 'line1' => '12 King Road', 'city' => 'Riyadh'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
        $store = $this->customer->company;
        $co = Company::create(['tenant_id' => $store->tenant_id, 'name' => 'Gulf Parts', 'type' => 'supplier', 'status' => 'active']);
        $this->seller = User::factory()->create(['email' => 'a@sellers.example.org', 'company_id' => $co->id]);
        $this->seller->assignRole('seller');
        $this->productId = $this->actingAs($this->seller, 'sanctum')->postJson('/api/products', ['name' => 'Brake pads', 'slug' => 'brake-pads-l', 'base_price' => 100, 'category' => 'spare-parts', 'requires_shipping' => true])->assertCreated()->json('id');
        $this->putJson("/api/products/{$this->productId}/price-rules", ['rules' => [['target_type' => 'vehicle_part', 'target_id' => $this->hood->id, 'price' => 100, 'is_active' => true]]])->assertOk();
    }

    private function cartFor(User $who): void
    {
        $this->actingAs($who, 'sanctum')->postJson('/api/cart/items', ['product_id' => $this->productId])->assertCreated();
    }

    /** A paid purchase for this customer, delivered by the seller. @return Order the delivered order */
    private function deliveredOrder(User $who): Order
    {
        $this->cartFor($who);
        $group = $this->postJson('/api/checkout', ['shipping_address' => $this->address])->assertCreated()->json();
        $this->postJson("/api/order-groups/{$group['id']}/payments", [])->assertOk();
        $order = Order::withoutGlobalScopes()->where('group_id', $group['id'])->firstOrFail();
        $ship = $this->actingAs($this->seller, 'sanctum')->postJson("/api/orders/{$order->id}/shipments", ['provider' => 'manual', 'carrier' => 'SMSA', 'tracking_number' => 'L'.$order->id])->assertCreated()->json();
        $this->postJson("/api/shipments/{$ship['id']}/status", ['status' => 'delivered', 'code' => Shipment::find($ship['id'])->delivery_code])->assertOk();

        return $order;
    }

    private function balance(User $u): int
    {
        return app(Loyalty::class)->redeemable($u, 0)['balance'];
    }

    public function test_goods_that_arrive_earn_points_once(): void
    {
        $order = $this->deliveredOrder($this->customer);
        $earned = (int) floor((float) $order->total_amount * 0.05);
        $this->assertGreaterThan(0, $earned);
        $this->assertSame($earned, $this->balance($this->customer));

        app(Loyalty::class)->goodsDelivered($order);                                                       // the same delivery replayed
        $this->assertSame($earned, $this->balance($this->customer));
        $this->assertSame(1, LoyaltyEntry::where('user_id', $this->customer->id)->where('ref', "order:{$order->id}")->count());
    }

    public function test_points_are_spent_up_to_a_share_of_the_purchase_and_come_back_when_it_is_dropped(): void
    {
        LoyaltyEntry::create(['user_id' => $this->customer->id, 'points' => 400, 'reason' => 'goods_order', 'ref' => 'seed']);
        $this->cartFor($this->customer);

        $points = $this->getJson('/api/cart')->assertOk()->json('points');
        $this->assertSame(400, $points['balance']);
        $this->assertSame(80, $points['max_use']);                                                         // 20% of 100 at 0.25 each

        $this->postJson('/api/checkout', ['shipping_address' => $this->address, 'use_points' => 81])->assertUnprocessable()->assertJsonValidationErrors('use_points');
        $group = $this->postJson('/api/checkout', ['shipping_address' => $this->address, 'use_points' => 80])->assertCreated()->json();
        $this->assertEquals(20, $group['discount_amount']);
        $this->assertEqualsWithDelta(100 + $group['service_fee_amount'] + ($group['shipping_amount'] ?? 0) - 20, $group['payable_amount'], 0.001);
        $this->assertSame(320, $this->balance($this->customer));

        $this->postJson("/api/order-groups/{$group['id']}/cancel")->assertOk();
        $this->assertSame(400, $this->balance($this->customer));                                           // nothing was bought, nothing is lost
        $this->assertSame(OrderGroup::CANCELLED, OrderGroup::find($group['id'])->status);
    }

    public function test_a_referral_rewards_both_people_once_when_the_friends_first_order_arrives(): void
    {
        $code = $this->actingAs($this->customer, 'sanctum')->getJson('/api/referral')->assertOk()->assertJsonPath('points_each', 50)->json('code');
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{8}$/', $code);
        $this->assertSame($code, $this->getJson('/api/referral')->json('code'));                            // the code is stable

        $challenge = $this->getJson('/api/signup-challenge')->json();
        $this->travel(5)->seconds();
        $this->postJson('/api/register', ['name' => 'Friend', 'email' => 'friend@example.org', 'signup_token' => $challenge['token'], 'password' => 'password123', 'password_confirmation' => 'password123',
            'company_id' => $this->customer->company_id, 'referral' => strtolower($code)])->assertCreated();
        $friend = User::where('email', 'friend@example.org')->firstOrFail();
        $this->assertSame($this->customer->id, $friend->referred_by);
        $this->assertSame(0, $this->balance($this->customer));                                              // nothing until an order arrives

        $this->deliveredOrder($friend);
        $friendEarned = (int) floor(100 * 0.05);
        $this->assertSame(50 + $friendEarned, $this->balance($friend));
        $this->assertSame(50, $this->balance($this->customer));
        $this->assertSame(1, $this->actingAs($this->customer, 'sanctum')->getJson('/api/referral')->json('invited'));
        $this->assertSame(1, $this->getJson('/api/referral')->json('rewarded'));

        $this->deliveredOrder($friend);                                                                     // a second order pays no second reward
        $this->assertSame(50, $this->balance($this->customer));
        $this->assertSame(50 + 2 * $friendEarned, $this->balance($friend));

        // A code that belongs to no one is ignored, and no one can refer themselves into extra points.
        $challenge = $this->getJson('/api/signup-challenge')->json();
        $this->travel(5)->seconds();
        $this->postJson('/api/register', ['name' => 'Other', 'email' => 'other@example.org', 'signup_token' => $challenge['token'], 'password' => 'password123', 'password_confirmation' => 'password123',
            'company_id' => $this->customer->company_id, 'referral' => 'NOSUCHCD'])->assertCreated();
        $this->assertNull(User::where('email', 'other@example.org')->value('referred_by'));
    }

    public function test_a_forgotten_cart_is_reminded_once_and_again_only_after_it_changes(): void
    {
        $this->cartFor($this->customer);
        $this->customer->forceFill(['locale' => 'ar'])->save();                                             // the language they last used
        $reminders = app(CartReminders::class);

        $this->assertSame(0, $reminders->run());                                                            // just filled: too soon
        $this->travel(25)->hours();
        $this->assertSame(1, $reminders->run());
        $note = $this->customer->notifications()->get()->first(fn ($n) => ($n->data['kind'] ?? null) === 'cart_reminder')->data;
        $this->assertSame('نسيت شيئاً في سلتك', $note['title']);                                           // in the customer's language
        $this->assertSame('/dashboard/cart', $note['path']);
        $this->assertSame(0, $reminders->run());                                                            // not twice for the same cart

        $this->travel(2)->hours();
        $this->actingAs($this->customer, 'sanctum')->postJson('/api/cart/items', ['product_id' => $this->productId])->assertCreated();   // touched again
        $this->travel(25)->hours();
        $this->assertSame(1, $reminders->run());

        // Switched off, or too old to bother with.
        app(Settings::class)->set('loyalty.cart_reminder_hours', 0);
        $this->assertSame(0, $reminders->run());
        app(Settings::class)->set('loyalty.cart_reminder_hours', 24);
        Cart::query()->update(['reminded_at' => null]);
        $this->travel(10)->days();
        $this->assertSame(0, $reminders->run());                                                            // a week-old cart is let go
    }

    public function test_a_refund_takes_back_points_earned_and_returns_points_spent(): void
    {
        LoyaltyEntry::create(['user_id' => $this->customer->id, 'points' => 400, 'reason' => 'goods_order', 'ref' => 'seed']);
        $this->cartFor($this->customer);
        $group = $this->postJson('/api/checkout', ['shipping_address' => $this->address, 'use_points' => 80])->assertCreated()->json();
        $this->postJson("/api/order-groups/{$group['id']}/payments", [])->assertOk();
        $order = Order::withoutGlobalScopes()->where('group_id', $group['id'])->firstOrFail();
        $this->assertSame(320, $this->balance($this->customer));                                              // 80 spent

        $ship = $this->actingAs($this->seller, 'sanctum')->postJson("/api/orders/{$order->id}/shipments", ['provider' => 'manual', 'carrier' => 'SMSA', 'tracking_number' => 'R'.$order->id])->assertCreated()->json();
        $this->postJson("/api/shipments/{$ship['id']}/status", ['status' => 'delivered', 'code' => Shipment::find($ship['id'])->delivery_code])->assertOk();
        $earned = $this->balance($this->customer) - 320;
        $this->assertGreaterThan(0, $earned);

        $this->actingAs($this->admin, 'sanctum')->postJson("/api/admin/orders/{$order->id}/refund", ['reason' => 'Product defect'])->assertOk();
        $this->assertSame(400, $this->balance($this->customer));                                              // earned points gone, spent points back: as if it never happened
        app(Loyalty::class)->refunded($order->fresh());                                                       // and again changes nothing
        $this->assertSame(400, $this->balance($this->customer));
    }
}
