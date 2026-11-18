<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\User;
use App\Services\Ledger\Accounts;
use App\Services\Ledger\Ledger;
use App\Services\Payment\GroupPayments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** A cart bought as one purchase: paid once, one order per line, each seller settled on its own. */
class CartCheckoutTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private Company $store;
    private User $sellerA;
    private User $sellerB;
    private array $address = ['name' => 'Layla', 'phone' => '0501234567', 'line1' => '12 King Road', 'city' => 'Riyadh'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
        $this->store = Company::withoutGlobalScopes()->where('name', 'Store')->first();
        $this->store->update(['slug' => 'main-store', 'settings' => ['storefront' => ['enabled' => true]]]);

        $make = function (string $name, string $email) {
            $co = Company::create(['tenant_id' => $this->store->tenant_id, 'name' => $name, 'type' => 'supplier', 'status' => 'active']);
            $u = User::factory()->create(['email' => $email, 'company_id' => $co->id]);
            $u->assignRole('seller');

            return $u;
        };
        $this->sellerA = $make('Gulf Parts', 'a@sellers.example.org');
        $this->sellerB = $make('Delta Film', 'b@sellers.example.org');
    }

    private function product(User $seller, string $name, int $price, array $extra = []): int
    {
        $id = $this->actingAs($seller, 'sanctum')->postJson('/api/products', $extra + ['name' => $name, 'slug' => str($name)->slug().'-x', 'base_price' => $price, 'category' => 'spare-parts'])->assertCreated()->json('id');
        $this->putJson("/api/products/{$id}/price-rules", ['rules' => [['target_type' => 'vehicle_part', 'target_id' => $this->hood->id, 'price' => $price, 'is_active' => true]]])->assertOk();

        return $id;
    }

    private function cartOf2(): array
    {
        $a = $this->product($this->sellerA, 'Brake pads', 100);
        $b = $this->product($this->sellerB, 'Film roll', 250);
        $this->actingAs($this->customer, 'sanctum');
        $this->postJson('/api/cart/items', ['product_id' => $a, 'quantity' => 2])->assertCreated();
        $this->postJson('/api/cart/items', ['product_id' => $b])->assertCreated();

        return [$a, $b];
    }

    public function test_the_cart_prices_every_line_and_merges_the_same_product(): void
    {
        [$a] = $this->cartOf2();

        $cart = $this->postJson('/api/cart/items', ['product_id' => $a, 'quantity' => 1])->assertCreated()->json();
        $this->assertCount(2, $cart['items']);
        $this->assertSame(3, $cart['items'][0]['quantity']);                 // 2 + 1, one line
        $this->assertEquals(300, $cart['items'][0]['line_total']);
        $this->assertEquals(550, $cart['items_amount']);
        $this->assertTrue($cart['can_checkout']);

        $id = $cart['items'][0]['id'];
        $this->putJson("/api/cart/items/{$id}", ['quantity' => 1])->assertOk()->assertJsonPath('items_amount', 350);
        $this->putJson("/api/cart/items/{$id}", ['quantity' => 99])->assertUnprocessable();
        $this->deleteJson("/api/cart/items/{$id}")->assertOk()->assertJsonCount(1, 'items');
    }

    public function test_only_what_may_be_bought_this_way_goes_in(): void
    {
        $this->actingAs($this->customer, 'sanctum');
        $this->postJson('/api/cart/items', ['product_id' => $this->product->id])->assertUnprocessable();     // a catalogue service, not a seller's goods
        $this->postJson('/api/cart/items', ['product_id' => 999999])->assertUnprocessable();

        $installOnly = $this->product($this->sellerA, 'Fit only', 80, ['installation' => 'required']);
        $this->actingAs($this->customer, 'sanctum')->postJson('/api/cart/items', ['product_id' => $installOnly])->assertUnprocessable();

        $hidden = $this->product($this->sellerA, 'Hidden', 80);
        $this->actingAs($this->sellerA, 'sanctum')->putJson("/api/products/{$hidden}", ['is_active' => false])->assertOk();
        $this->actingAs($this->customer, 'sanctum')->postJson('/api/cart/items', ['product_id' => $hidden])->assertUnprocessable();
    }

    public function test_a_cart_is_private_to_its_customer(): void
    {
        $this->cartOf2();
        $item = $this->getJson('/api/cart')->json('items.0.id');

        $other = User::factory()->create(['company_id' => $this->store->id]);
        $other->assignRole('customer');
        $this->actingAs($other, 'sanctum')->getJson('/api/cart')->assertOk()->assertJsonCount(0, 'items');
        $this->putJson("/api/cart/items/{$item}", ['quantity' => 5])->assertNotFound();
        $this->deleteJson("/api/cart/items/{$item}")->assertOk();
        $this->actingAs($this->customer, 'sanctum')->getJson('/api/cart')->assertJsonCount(2, 'items');   // untouched
    }

    public function test_checkout_makes_one_order_per_line_under_one_group_and_empties_the_cart(): void
    {
        $this->cartOf2();

        $group = $this->postJson('/api/checkout', ['shipping_address' => $this->address])->assertCreated()->json();
        $this->assertSame('awaiting_payment', $group['status']);
        $this->assertCount(2, $group['orders']);
        $this->assertEquals(450, $group['items_amount']);    // 2 x 100 and 1 x 250
        $this->assertEqualsWithDelta(450.0 + $group['service_fee_amount'], $group['payable_amount'], 0.001);
        $this->getJson('/api/cart')->assertJsonCount(0, 'items');

        $orders = Order::withoutGlobalScopes()->where('group_id', $group['id'])->orderBy('id')->get();
        $this->assertSame([2, 1], $orders->pluck('quantity')->all());
        $this->assertSame([200.0, 250.0], $orders->map(fn ($o) => (float) $o->total_amount)->all());
        $this->assertCount(1, array_unique($orders->map(fn ($o) => $o->shipping_address['line1'].$o->shipping_address['phone'])->all()));    // every order ships to the same address
        $this->assertSame(0, $this->postJson('/api/checkout', ['shipping_address' => $this->address])->status() === 422 ? 0 : 1);   // an empty cart cannot be bought
    }

    public function test_paying_the_group_pays_every_order_and_settles_each_seller_separately(): void
    {
        [$a, $b] = $this->cartOf2();
        $group = $this->postJson('/api/checkout', ['shipping_address' => $this->address])->assertCreated()->json();

        $this->postJson("/api/order-groups/{$group['id']}/payments", ['payment_method' => 'card'])->assertOk()->assertJsonPath('status', 'paid');

        $this->assertSame('paid', OrderGroup::find($group['id'])->status);
        $orders = Order::withoutGlobalScopes()->where('group_id', $group['id'])->get();
        $this->assertTrue($orders->every(fn ($o) => $o->status === OrderStatus::Paid));

        // One payment per order, adding up to what the customer was asked to pay.
        $paid = \App\Models\Payment::withoutGlobalScopes()->whereIn('order_id', $orders->pluck('id'))->sum('amount');
        $this->assertEqualsWithDelta($group['payable_amount'], (float) $paid, 0.001);

        // Each seller is owed their own sale less commission, in their own account; the ledger agrees with the records (checked after every test too).
        $ledger = app(Ledger::class);
        $commission = fn ($o) => (float) \App\Models\Commission::where('order_id', $o->id)->value('amount');
        foreach ($orders as $o) {
            $seller = \App\Models\Product::find($o->product_id)->seller_id;
            $this->assertEqualsWithDelta((float) $o->total_amount - $commission($o), $ledger->balance(Accounts::sellerHeld($seller), $o->currency), 0.001);
        }
        $this->assertEqualsWithDelta($group['payable_amount'], $ledger->balance(Accounts::GATEWAY, $group['currency']), 0.001);

        // Paying twice is refused.
        $this->postJson("/api/order-groups/{$group['id']}/payments", [])->assertUnprocessable();
    }

    public function test_a_group_belongs_to_its_customer_alone(): void
    {
        $this->cartOf2();
        $group = $this->postJson('/api/checkout', ['shipping_address' => $this->address])->json();

        $other = User::factory()->create(['company_id' => $this->store->id]);
        $other->assignRole('customer');
        $this->actingAs($other, 'sanctum');
        $this->getJson("/api/order-groups/{$group['id']}")->assertNotFound();
        $this->postJson("/api/order-groups/{$group['id']}/payments", [])->assertNotFound();
        $this->postJson("/api/order-groups/{$group['id']}/cancel")->assertNotFound();
        $this->assertSame([], $this->getJson('/api/order-groups')->json());
    }

    public function test_a_purchase_can_be_dropped_before_it_is_paid(): void
    {
        $this->cartOf2();
        $group = $this->postJson('/api/checkout', ['shipping_address' => $this->address])->json();

        $this->postJson("/api/order-groups/{$group['id']}/cancel")->assertOk()->assertJsonPath('status', 'cancelled');
        $this->assertTrue(Order::withoutGlobalScopes()->where('group_id', $group['id'])->get()->every(fn ($o) => $o->status === OrderStatus::Cancelled));
        $this->postJson("/api/order-groups/{$group['id']}/payments", [])->assertUnprocessable();
    }

    public function test_a_provider_confirmation_must_match_the_group_total_exactly(): void
    {
        $this->cartOf2();
        $group = OrderGroup::find($this->postJson('/api/checkout', ['shipping_address' => $this->address])->json('id'));
        $payments = app(GroupPayments::class);

        // Too little, or the wrong currency: nothing is paid.
        $payments->webhookPaid($group->id, 'txn-1', (int) round(($group->payable_amount - 1) * 100), null, $group->currency, 'test');
        $wrong = $group->currency === 'EUR' ? 'USD' : 'EUR';
        $payments->webhookPaid($group->id, 'txn-1', (int) round($group->payable_amount * 100), null, $wrong, 'test');
        $this->assertSame('awaiting_payment', $group->fresh()->status);

        // Exactly right: paid, once.
        $payments->webhookPaid($group->id, 'txn-2', (int) round($group->payable_amount * 100), null, $group->currency, 'test');
        $payments->webhookPaid($group->id, 'txn-2', (int) round($group->payable_amount * 100), null, $group->currency, 'test');
        $this->assertSame('paid', $group->fresh()->status);
        $this->assertSame(2, \App\Models\Payment::withoutGlobalScopes()->whereIn('order_id', $group->orders()->pluck('id'))->count());
    }

    public function test_a_payment_id_in_the_group_range_is_not_mistaken_for_an_order_or_a_job(): void
    {
        $this->assertSame(7, OrderGroup::fromPaymentId(-(OrderGroup::PAYMENT_ID_OFFSET + 7)));
        $this->assertNull(OrderGroup::fromPaymentId(-42));       // an installation job
        $this->assertNull(OrderGroup::fromPaymentId(42));        // an order
    }

    public function test_each_part_of_an_order_has_its_own_status(): void
    {
        [$a] = $this->cartOf2();
        $group = $this->postJson('/api/checkout', ['shipping_address' => $this->address])->json();
        $order = Order::withoutGlobalScopes()->where('group_id', $group['id'])->orderBy('id')->first();
        // The seller's settlement is between the seller and the platform: the customer's own view leaves it out, the platform's shows it.
        $statuses = fn () => $this->actingAs($this->admin, 'sanctum')->getJson("/api/orders/{$order->id}")->assertOk()->json('statuses');
        $asCustomer = fn () => $this->actingAs($this->customer, 'sanctum')->getJson("/api/orders/{$order->id}")->assertOk()->json('statuses');

        // Not paid yet: nothing has started, and a goods order has no service to speak of.
        $this->assertSame(['order' => 'pending_payment', 'payment' => 'pending', 'product' => 'pending', 'service' => null, 'settlement' => null], $statuses());

        $this->actingAs($this->customer, 'sanctum')->postJson("/api/order-groups/{$group['id']}/payments", [])->assertOk();
        $this->assertSame(['order' => 'confirmed', 'payment' => 'paid', 'product' => 'processing', 'service' => null, 'settlement' => 'pending'], $statuses());
        $this->assertArrayNotHasKey('settlement', $asCustomer());

        // The parcel is booked and then moves: the goods change while the order and the payment stay as they were.
        $shipment = \App\Models\Shipment::create(['order_id' => $order->id, 'provider' => 'manual', 'status' => \App\Models\Shipment::CREATED]);
        $this->assertSame('ready', $statuses()['product']);
        $shipment->update(['status' => \App\Models\Shipment::IN_TRANSIT]);
        $this->assertSame(['confirmed', 'paid', 'shipped', 'pending'], array_values(array_diff_key($statuses(), ['service' => 1])));
        $shipment->update(['status' => \App\Models\Shipment::DELIVERED]);
        $this->assertSame('delivered', $statuses()['product']);

        // Delivery makes the seller's money eligible; a payout moves it on. Only the settlement changes.
        \App\Models\SellerLedgerEntry::where('order_id', $order->id)->update(['status' => 'available']);
        app(\App\Services\Ledger\LedgerPostings::class)->sellerReleased($order);
        $this->assertSame('eligible', $statuses()['settlement']);

        $sellerCompany = Company::withoutGlobalScopes()->find(\App\Models\Product::find($order->product_id)->seller_id);
        $payout = app(\App\Services\SellerPayoutService::class)->create($sellerCompany, $order->currency, $this->admin);
        $this->assertSame('processing', $statuses()['settlement']);
        app(\App\Services\SellerPayoutService::class)->markPaid($payout, 'bank-ref-1', $this->admin);
        $this->assertSame('paid', $statuses()['settlement']);
        $this->assertSame('confirmed', $statuses()['order']);
    }

    public function test_a_service_order_has_a_service_status_and_no_goods_status(): void
    {
        $this->actingAs($this->customer, 'sanctum');
        $order = Order::withoutGlobalScopes()->create([
            'company_id' => $this->customer->company_id, 'customer_id' => $this->customer->id, 'product_id' => $this->product->id, 'status' => OrderStatus::PendingQuotes,
            'product_amount' => 100, 'service_amount' => 0, 'commission_amount' => 0, 'total_amount' => 100, 'currency' => 'SAR',
        ]);
        $service = app(\App\Services\OrderStatuses::class);

        $expect = [
            [OrderStatus::PendingQuotes, 'draft', 'requested'], [OrderStatus::QuotesReceived, 'draft', 'quoted'], [OrderStatus::PartnerSelected, 'pending_payment', 'accepted'],
            [OrderStatus::Paid, 'confirmed', 'booked'], [OrderStatus::ServiceInProgress, 'confirmed', 'in_progress'], [OrderStatus::Completed, 'completed', 'completed'],
            [OrderStatus::Cancelled, 'cancelled', 'cancelled'],
        ];
        foreach ($expect as [$status, $orderState, $serviceState]) {
            $order->update(['status' => $status]);
            $got = $service->of($order->fresh());
            $this->assertSame([$orderState, $serviceState, null], [$got['order'], $got['service'], $got['product']], $status->value);
        }
    }

    private function shippingRules(float $flat, ?float $freeOver = null, ?int $eta = null): void
    {
        // Delivery fees are per market, so the test marketplace gets one (Saudi Arabia: the currency the test prices are in).
        \App\Models\Tenant::whereKey($this->store->tenant_id)->update(['market' => 'SA']);
        $code = 'SA';
        $settings = app(\App\Support\Settings\Settings::class);
        $settings->set("shipping.flat_fee.{$code}", $flat);
        $settings->set("shipping.free_over.{$code}", $freeOver);
        $settings->set("shipping.eta_days.{$code}", $eta);
    }

    public function test_delivery_is_charged_once_per_seller_and_travels_to_the_seller(): void
    {
        $this->shippingRules(30, null, 3);
        [$a, $b] = $this->cartOf2();                                                                // 2 x 100 from A, 1 x 250 from B

        $cart = $this->getJson('/api/cart')->assertOk()->json();
        $this->assertEquals(60, $cart['shipping_amount']);                                          // one parcel for each seller
        $this->assertSame(3, $cart['eta_days']);
        $this->assertEqualsWithDelta(450 + $cart['service_fee_amount'] + 60, $cart['payable_amount'], 0.001);

        $group = $this->postJson('/api/checkout', ['shipping_address' => $this->address])->assertCreated()->json();
        $this->assertEquals(60, $group['shipping_amount'] ?? OrderGroup::find($group['id'])->shipping_amount);
        $orders = Order::withoutGlobalScopes()->where('group_id', $group['id'])->orderBy('id')->get();
        $this->assertSame([30.0, 30.0], $orders->map(fn ($o) => (float) $o->shipping_amount)->all());
        $this->assertEqualsWithDelta($cart['payable_amount'], (float) OrderGroup::find($group['id'])->payable_amount, 0.001);

        $this->postJson("/api/order-groups/{$group['id']}/payments", [])->assertOk();
        $ledger = app(Ledger::class);
        $commission = fn ($o) => (float) \App\Models\Commission::where('order_id', $o->id)->value('amount');
        foreach ($orders as $o) {
            $seller = \App\Models\Product::find($o->product_id)->seller_id;
            $this->assertEqualsWithDelta((float) $o->total_amount - $commission($o) + 30, $ledger->balance(Accounts::sellerHeld($seller), $o->currency), 0.001);   // no commission on delivery
        }
        $this->assertEqualsWithDelta($cart['payable_amount'], $ledger->balance(Accounts::GATEWAY, $group['currency']), 0.001);
    }

    public function test_delivery_is_free_above_the_threshold_and_when_no_fee_is_set(): void
    {
        $this->shippingRules(30, 200);
        $this->cartOf2();                                                                           // A: 200 (reaches the threshold), B: 250 (also)
        $this->assertEquals(0, $this->getJson('/api/cart')->json('shipping_amount'));

        $this->shippingRules(30, 220);                                                              // A: 200 pays, B: 250 is free
        $this->assertEquals(30, $this->getJson('/api/cart')->json('shipping_amount'));

        $this->shippingRules(0);                                                                    // no fee set: as before
        $cart = $this->getJson('/api/cart')->json();
        $this->assertEquals(0, $cart['shipping_amount']);
        $this->assertNull($cart['eta_days']);
    }

    public function test_a_refund_gives_back_the_delivery_too(): void
    {
        $this->shippingRules(30);
        $this->cartOf2();
        $group = $this->postJson('/api/checkout', ['shipping_address' => $this->address])->json();
        $this->postJson("/api/order-groups/{$group['id']}/payments", [])->assertOk();
        $order = Order::withoutGlobalScopes()->where('group_id', $group['id'])->orderBy('id')->first();
        $expected = (float) $order->total_amount + (float) $order->service_fee_amount + 30;

        $refund = $this->actingAs($this->admin, 'sanctum')->postJson("/api/admin/orders/{$order->id}/refund", ['reason' => 'Delivery test'])->assertOk()->json('refund_amount');
        $this->assertEqualsWithDelta($expected, $refund, 0.001);
        $seller = \App\Models\Product::find($order->product_id)->seller_id;
        $this->assertEqualsWithDelta(0, app(Ledger::class)->balance(Accounts::sellerHeld($seller), $order->currency), 0.001);
    }

    private function promo(array $extra = []): \App\Models\PromoCode
    {
        return \App\Models\PromoCode::create($extra + ['code' => 'SAVE10', 'type' => 'percent', 'value' => 10, 'per_customer_limit' => 1]);
    }

    public function test_a_discount_code_lowers_the_payment_and_the_platform_bears_it(): void
    {
        $this->promo();                                                                              // 10% off the items
        $this->cartOf2();                                                                            // items 450
        $cart = $this->postJson('/api/cart/promo', ['code' => ' save10 '])->assertOk()->json();
        $this->assertSame('SAVE10', $cart['promo_code']);
        $this->assertEquals(45, $cart['discount_amount']);
        $this->assertEqualsWithDelta(450 + $cart['service_fee_amount'] - 45, $cart['payable_amount'], 0.001);

        $group = $this->postJson('/api/checkout', ['shipping_address' => $this->address])->assertCreated()->json();
        $this->assertEqualsWithDelta($cart['payable_amount'], $group['payable_amount'], 0.001);
        $orders = Order::withoutGlobalScopes()->where('group_id', $group['id'])->orderBy('id')->get();
        $this->assertEqualsWithDelta(45, $orders->sum(fn ($o) => (float) $o->discount_amount), 0.001);           // shared across the orders
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('cart_items')->count());                       // the cart is empty, and the code is spent from it
        $this->getJson('/api/cart')->assertJsonPath('promo_code', null);

        $this->postJson("/api/order-groups/{$group['id']}/payments", [])->assertOk();
        $ledger = app(Ledger::class);
        $this->assertEqualsWithDelta($group['payable_amount'], $ledger->balance(Accounts::GATEWAY, $group['currency']), 0.001);   // the customer paid less
        $this->assertEqualsWithDelta(45, $ledger->balance(Accounts::EXP_PROMO, $group['currency']), 0.001);                          // the platform paid the difference
        foreach ($orders as $o) {
            $seller = \App\Models\Product::find($o->product_id)->seller_id;
            $commission = (float) \App\Models\Commission::where('order_id', $o->id)->value('amount');
            $this->assertEqualsWithDelta((float) $o->total_amount - $commission, $ledger->balance(Accounts::sellerHeld($seller), $o->currency), 0.001);   // the seller still gets everything
        }

        // Refunding an order gives back what the customer paid, and the discount stops being the platform's cost.
        $first = $orders->first();
        $paid = (float) $first->total_amount + (float) $first->service_fee_amount - (float) $first->discount_amount;
        $refund = $this->actingAs($this->admin, 'sanctum')->postJson("/api/admin/orders/{$first->id}/refund", ['reason' => 'Discount test'])->assertOk()->json('refund_amount');
        $this->assertEqualsWithDelta($paid, $refund, 0.001);
    }

    public function test_a_code_is_refused_with_the_reason_and_limits_are_kept(): void
    {
        [$a] = $this->cartOf2();
        $this->postJson('/api/cart/promo', ['code' => 'NOPE'])->assertUnprocessable()->assertJsonValidationErrors('code');

        $this->promo(['code' => 'BIG', 'min_order' => 1000]);
        $this->postJson('/api/cart/promo', ['code' => 'BIG'])->assertUnprocessable();                 // the cart is 450
        $this->promo(['code' => 'OLD', 'ends_at' => now()->subDay()]);
        $this->postJson('/api/cart/promo', ['code' => 'OLD'])->assertUnprocessable();
        $this->promo(['code' => 'OFF', 'is_active' => false]);
        $this->postJson('/api/cart/promo', ['code' => 'OFF'])->assertUnprocessable();
        $this->promo(['code' => 'FIXED', 'type' => 'fixed', 'value' => 1000]);                        // never more than the items
        $this->assertEquals(450, $this->postJson('/api/cart/promo', ['code' => 'FIXED'])->assertOk()->json('discount_amount'));
        $this->promo(['code' => 'CAPPED', 'value' => 50, 'max_discount' => 20]);
        $this->assertEquals(20, $this->postJson('/api/cart/promo', ['code' => 'CAPPED'])->assertOk()->json('discount_amount'));
        $this->deleteJson('/api/cart/promo')->assertOk()->assertJsonPath('promo_code', null);

        // One use each: after buying with it, the same customer cannot use it again; cancelling the purchase gives it back.
        $this->promo(['code' => 'ONCE', 'value' => 10]);
        $this->postJson('/api/cart/promo', ['code' => 'ONCE'])->assertOk();
        $group = $this->postJson('/api/checkout', ['shipping_address' => $this->address])->assertCreated()->json();
        $this->postJson('/api/cart/items', ['product_id' => $a, 'quantity' => 2])->assertCreated();
        $this->postJson('/api/cart/promo', ['code' => 'ONCE'])->assertUnprocessable();
        $this->postJson("/api/order-groups/{$group['id']}/cancel")->assertOk();
        $this->postJson('/api/cart/promo', ['code' => 'ONCE'])->assertOk();
    }

    public function test_the_administrator_manages_discount_codes(): void
    {
        $this->actingAs($this->customer, 'sanctum')->getJson('/api/admin/promo-codes')->assertForbidden();
        $this->actingAs($this->admin, 'sanctum');
        $id = $this->postJson('/api/admin/promo-codes', ['code' => 'ramadan 25', 'type' => 'percent', 'value' => 25, 'max_discount' => 100, 'market' => 'eg'])->assertCreated()->assertJsonPath('code', 'RAMADAN25')->assertJsonPath('market', 'EG')->json('id');
        $this->postJson('/api/admin/promo-codes', ['code' => 'RAMADAN25', 'type' => 'fixed', 'value' => 5])->assertUnprocessable();       // codes are unique
        $this->postJson('/api/admin/promo-codes', ['code' => 'TOOMUCH', 'type' => 'percent', 'value' => 150])->assertUnprocessable();
        $this->putJson("/api/admin/promo-codes/{$id}", ['is_active' => false])->assertOk()->assertJsonPath('is_active', false);
        $this->getJson('/api/admin/promo-codes')->assertOk()->assertJsonCount(1)->assertJsonPath('0.used', 0);
    }

    public function test_people_who_saved_a_product_hear_when_its_price_drops(): void
    {
        $id = $this->product($this->sellerA, 'Brake pads', 100);
        \Illuminate\Support\Facades\DB::table('favourites')->insert(['user_id' => $this->customer->id, 'product_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
        $told = $this->customer->notifications()->count();
        $rule = fn (int $price) => ['rules' => [['target_type' => 'vehicle_part', 'target_id' => $this->hood->id, 'price' => $price, 'is_active' => true]]];

        $this->actingAs($this->sellerA, 'sanctum')->putJson("/api/products/{$id}/price-rules", $rule(120))->assertOk();      // up: no one is told
        $this->assertSame($told, $this->customer->notifications()->count());
        $this->putJson("/api/products/{$id}/price-rules", $rule(90))->assertOk();                                           // down: the customer is told
        $this->assertSame($told + 1, $this->customer->notifications()->count());
        $this->putJson("/api/products/{$id}/price-rules", $rule(90))->assertOk();                                           // unchanged: no one is told again
        $this->assertSame($told + 1, $this->customer->notifications()->count());
        $this->assertStringContainsString('Brake pads', $this->customer->notifications()->latest()->first()->data['body']);
    }

    public function test_the_purchase_lists_each_orders_statuses(): void
    {
        $this->cartOf2();
        $group = $this->postJson('/api/checkout', ['shipping_address' => $this->address])->json();
        $this->postJson("/api/order-groups/{$group['id']}/payments", [])->assertOk();

        $orders = $this->getJson("/api/order-groups/{$group['id']}")->assertOk()->json('orders');
        $this->assertCount(2, $orders);
        foreach ($orders as $o) {
            $this->assertSame('paid', $o['statuses']['payment']);
            $this->assertSame('processing', $o['statuses']['product']);
        }
    }
}
