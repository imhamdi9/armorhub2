<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductQuote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** Buying against a quote: ask, receive numbered quotes, accept one, pay. */
class QuoteRequestTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private Company $store;
    private User $sellerA;
    private User $sellerB;
    private User $sellerC;
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
        $this->sellerB = $make('Delta Parts', 'b@sellers.example.org');
        $this->sellerC = $make('Tyre House', 'c@sellers.example.org');
        foreach (['brakes', 'tyres'] as $i => $key) {
            \App\Models\ProductCategory::firstOrCreate(['key' => $key], ['name_en' => ucfirst($key), 'name_ar' => $key, 'position' => $i + 5, 'is_active' => true]);
        }
    }

    private function product(User $seller, string $name, string $category = 'spare-parts', array $extra = []): int
    {
        $id = $this->actingAs($seller, 'sanctum')->postJson('/api/products', $extra + ['name' => $name, 'slug' => str($name)->slug().'-x', 'base_price' => 100, 'category' => $category])->assertCreated()->json('id');
        $this->putJson("/api/products/{$id}/price-rules", ['rules' => [['target_type' => 'vehicle_part', 'target_id' => $this->hood->id, 'price' => 100, 'is_active' => true]]])->assertOk();

        return $id;
    }

    public function test_a_quote_only_product_cannot_be_bought_at_a_price(): void
    {
        $id = $this->product($this->sellerA, 'Custom exhaust', 'spare-parts', ['sale_mode' => 'quote']);
        $cards = collect($this->getJson('/api/public/storefronts/main-store/products')->json('data'));
        $this->assertSame('quote', $cards->firstWhere('name', 'Custom exhaust')['sale_mode'] ?? null);

        $this->actingAs($this->customer, 'sanctum');
        $this->postJson('/api/cart/items', ['product_id' => $id])->assertUnprocessable();
        $this->postJson('/api/orders', ['product_id' => $id, 'target_type' => 'vehicle_part', 'targets' => [$this->hood->id], 'shipping_address' => $this->address + ['country' => 'SA']])->assertUnprocessable();
        $this->postJson('/api/guest-orders', ['store' => 'main-store', 'product_id' => $id, 'name' => 'G', 'email' => 'g@example.org', 'phone' => '0501234567', 'address' => ['line1' => '1', 'city' => 'Riyadh']])->assertUnprocessable();
    }

    public function test_a_quote_for_a_listed_product_goes_to_its_seller_and_becomes_a_paid_order(): void
    {
        $id = $this->product($this->sellerA, 'Custom exhaust', 'spare-parts', ['sale_mode' => 'quote']);
        $this->actingAs($this->customer, 'sanctum');

        $req = $this->postJson('/api/quote-requests', ['product_id' => $id, 'quantity' => 2, 'notes' => 'Stainless please'])->assertCreated()->json();
        $this->assertMatchesRegularExpression('/^RFQ-\d{4}-\d{6}$/', $req['number']);

        // Only the product's own seller is asked, and sees what is needed but not who asks.
        $this->actingAs($this->sellerB, 'sanctum');
        $this->assertSame([], $this->getJson('/api/seller/quote-requests')->json());
        $this->postJson("/api/seller/quote-requests/{$req['id']}/quote", ['unit_price' => 50])->assertNotFound();

        $this->actingAs($this->sellerA, 'sanctum');
        $list = $this->getJson('/api/seller/quote-requests')->assertOk()->json();
        $this->assertSame([$req['id']], array_column($list, 'id'));
        $this->assertArrayNotHasKey('customer_id', $list[0]);

        $quote = $this->postJson("/api/seller/quote-requests/{$req['id']}/quote", ['unit_price' => 480, 'lead_days' => 3, 'condition' => 'aftermarket', 'warranty_days' => 180, 'note' => 'Ready in 3 days', 'valid_days' => 5])->assertCreated()->json();
        $this->assertMatchesRegularExpression('/^QT-\d{4}-\d{6}$/', $quote['number']);
        $this->assertSame(1, $this->customer->notifications()->count());

        $this->actingAs($this->customer, 'sanctum');
        $shown = $this->getJson("/api/quote-requests/{$req['id']}")->assertOk()->json();
        $this->assertEquals(960, $shown['quotes'][0]['total']);                         // 480 x 2
        $this->assertStringStartsWith('S-', $shown['quotes'][0]['seller']);               // a code, not the seller's name

        $accepted = $this->postJson("/api/quote-requests/{$req['id']}/accept", ['quote_id' => $quote['id'], 'shipping_address' => $this->address])->assertCreated()->json();
        $order = Order::withoutGlobalScopes()->where('group_id', $accepted['order_group_id'])->first();
        $this->assertEquals([2, 960], [$order->quantity, (float) $order->total_amount]);   // the quoted price, not the list price

        $this->postJson("/api/order-groups/{$accepted['order_group_id']}/payments", [])->assertOk()->assertJsonPath('status', 'paid');
        $this->assertSame('accepted', $this->getJson("/api/quote-requests/{$req['id']}")->json('status'));
        $this->postJson("/api/quote-requests/{$req['id']}/accept", ['quote_id' => $quote['id'], 'shipping_address' => $this->address])->assertUnprocessable();   // only once
    }

    public function test_an_accepted_quote_carries_delivery_like_any_purchase(): void
    {
        \App\Models\Tenant::whereKey($this->customer->company->tenant_id)->update(['market' => 'SA']);
        app(\App\Support\Settings\Settings::class)->set('shipping.flat_fee.SA', 35);

        $id = $this->product($this->sellerA, 'Custom exhaust', 'spare-parts', ['sale_mode' => 'quote']);
        $this->actingAs($this->customer, 'sanctum');
        $req = $this->postJson('/api/quote-requests', ['product_id' => $id, 'quantity' => 1])->assertCreated()->json();
        $this->actingAs($this->sellerA, 'sanctum');
        $quote = $this->postJson("/api/seller/quote-requests/{$req['id']}/quote", ['unit_price' => 400])->assertCreated()->json();

        $this->actingAs($this->customer, 'sanctum');
        $accepted = $this->postJson("/api/quote-requests/{$req['id']}/accept", ['quote_id' => $quote['id'], 'shipping_address' => $this->address])->assertCreated()->json();
        $order = Order::withoutGlobalScopes()->where('group_id', $accepted['order_group_id'])->first();
        $this->assertEquals(35, (float) $order->shipping_amount);
        $this->assertEqualsWithDelta(400 + (float) $order->service_fee_amount + 35, $accepted['payable_amount'], 0.001);
        $this->postJson("/api/order-groups/{$accepted['order_group_id']}/payments", [])->assertOk()->assertJsonPath('status', 'paid');
    }

    public function test_a_sourcing_request_asks_the_sellers_of_that_kind_and_the_cheapest_is_shown_first(): void
    {
        $this->product($this->sellerA, 'Brake pads', 'brakes');
        $b = $this->product($this->sellerB, 'Brake discs', 'brakes');
        $this->product($this->sellerC, 'Tyre', 'tyres');

        $this->actingAs($this->customer, 'sanctum');
        $this->postJson('/api/quote-requests', ['title' => 'Rear brake caliper', 'quantity' => 1])->assertUnprocessable();                       // a category is needed
        $this->postJson('/api/quote-requests', ['title' => 'Call me 01012345678', 'category' => 'brakes', 'quantity' => 1])->assertUnprocessable();  // no contact details
        $this->postJson('/api/quote-requests', ['title' => 'Alien part', 'category' => 'warp-drives', 'quantity' => 1])->assertUnprocessable();     // nobody sells that
        $req = $this->postJson('/api/quote-requests', ['title' => 'Rear brake caliper', 'category' => 'brakes', 'quantity' => 1])->assertCreated()->json();

        $seen = fn (User $u) => $this->actingAs($u, 'sanctum')->getJson('/api/seller/quote-requests')->json();
        $this->assertCount(1, $seen($this->sellerA));
        $this->assertCount(1, $seen($this->sellerB));
        $this->assertCount(0, $seen($this->sellerC));                                     // sells tyres, not brakes

        // A seller must name one of its own products that fills the request.
        $notMine = $this->product($this->sellerA, 'Not mine', 'brakes');
        $this->actingAs($this->sellerB, 'sanctum');
        $this->postJson("/api/seller/quote-requests/{$req['id']}/quote", ['unit_price' => 700])->assertUnprocessable();
        $this->postJson("/api/seller/quote-requests/{$req['id']}/quote", ['unit_price' => 700, 'product_id' => $notMine])->assertUnprocessable();
        $this->postJson("/api/seller/quote-requests/{$req['id']}/quote", ['unit_price' => 700, 'product_id' => $b])->assertCreated();
        $aProduct = Product::where('seller_id', $this->sellerA->company_id)->value('id');
        $this->actingAs($this->sellerA, 'sanctum')->postJson("/api/seller/quote-requests/{$req['id']}/quote", ['unit_price' => 550, 'product_id' => $aProduct])->assertCreated();

        $quotes = $this->actingAs($this->customer, 'sanctum')->getJson("/api/quote-requests/{$req['id']}")->json('quotes');
        $this->assertEquals([550, 700], array_column($quotes, 'unit_price'));
        $this->assertNotSame($quotes[0]['seller'], $quotes[1]['seller']);

        // Accepting one closes the others.
        $this->postJson("/api/quote-requests/{$req['id']}/accept", ['quote_id' => $quotes[1]['id'], 'shipping_address' => $this->address])->assertCreated();
        $this->assertSame(['accepted', 'rejected'], ProductQuote::where('request_id', $req['id'])->orderBy('unit_price', 'desc')->pluck('status')->all());
    }

    public function test_a_quote_holds_for_its_validity_and_the_quantity_must_be_met(): void
    {
        $id = $this->product($this->sellerA, 'Custom exhaust', 'spare-parts', ['sale_mode' => 'quote']);
        $req = $this->actingAs($this->customer, 'sanctum')->postJson('/api/quote-requests', ['product_id' => $id, 'quantity' => 5])->json();

        $this->actingAs($this->sellerA, 'sanctum')->postJson("/api/seller/quote-requests/{$req['id']}/quote", ['unit_price' => 100, 'available_qty' => 2])->assertUnprocessable();    // cannot supply 5
        foreach ([['unit_price' => 0], ['unit_price' => 100, 'valid_days' => 99], ['unit_price' => 100, 'condition' => 'broken'], ['unit_price' => 100, 'note' => 'whatsapp me']] as $bad) {
            $this->postJson("/api/seller/quote-requests/{$req['id']}/quote", $bad)->assertUnprocessable();
        }
        $quote = $this->postJson("/api/seller/quote-requests/{$req['id']}/quote", ['unit_price' => 100, 'available_qty' => 10, 'valid_days' => 3])->assertCreated()->json();

        // Re-quoting keeps the same number and replaces the price.
        $again = $this->postJson("/api/seller/quote-requests/{$req['id']}/quote", ['unit_price' => 90, 'valid_days' => 3])->assertCreated()->json();
        $this->assertSame($quote['number'], $again['number']);

        // Past its validity it cannot be accepted.
        $this->actingAs($this->customer, 'sanctum');
        ProductQuote::where('request_id', $req['id'])->update(['valid_until' => now()->subMinute()]);
        $this->postJson("/api/quote-requests/{$req['id']}/accept", ['quote_id' => $quote['id'], 'shipping_address' => $this->address])->assertUnprocessable();
        $this->assertTrue($this->getJson("/api/quote-requests/{$req['id']}")->json('quotes.0.expired'));
    }

    public function test_requests_are_private_and_can_be_closed_or_expire(): void
    {
        $id = $this->product($this->sellerA, 'Custom exhaust', 'spare-parts', ['sale_mode' => 'quote']);
        $req = $this->actingAs($this->customer, 'sanctum')->postJson('/api/quote-requests', ['product_id' => $id, 'quantity' => 1])->json();

        $other = User::factory()->create(['company_id' => $this->store->id]);
        $other->assignRole('customer');
        $this->actingAs($other, 'sanctum');
        $this->getJson("/api/quote-requests/{$req['id']}")->assertNotFound();
        $this->postJson("/api/quote-requests/{$req['id']}/close")->assertNotFound();
        $this->assertSame([], $this->getJson('/api/quote-requests')->json());

        $this->actingAs($this->customer, 'sanctum')->postJson("/api/quote-requests/{$req['id']}/close")->assertOk()->assertJsonPath('status', 'closed');
        $this->actingAs($this->sellerA, 'sanctum')->postJson("/api/seller/quote-requests/{$req['id']}/quote", ['unit_price' => 100])->assertUnprocessable();    // closed
        $this->assertSame([], $this->getJson('/api/seller/quote-requests')->json());

        $second = $this->actingAs($this->customer, 'sanctum')->postJson('/api/quote-requests', ['product_id' => $id, 'quantity' => 1])->json();
        \App\Models\ProductQuoteRequest::whereKey($second['id'])->update(['expires_at' => now()->subHour()]);
        $this->assertSame('expired', $this->getJson("/api/quote-requests/{$second['id']}")->json('status'));
        $this->actingAs($this->sellerA, 'sanctum')->postJson("/api/seller/quote-requests/{$second['id']}/quote", ['unit_price' => 100])->assertUnprocessable();
    }

    public function test_document_numbers_never_repeat_and_run_per_year(): void
    {
        $n = app(\App\Services\DocumentNumbers::class);
        $a = $n->next('RFQ');
        $b = $n->next('RFQ');
        $c = $n->next('QT');

        $this->assertNotSame($a, $b);
        $this->assertSame(substr($a, 0, 9), substr($b, 0, 9));
        $this->assertStringEndsWith('000001', $a);
        $this->assertStringEndsWith('000002', $b);
        $this->assertStringEndsWith('000001', $c);                    // each kind counts on its own
    }
}
