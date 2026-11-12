<?php

namespace Tests\Feature;

use App\Core\Events\EventBus;
use App\Core\Events\Models\EventRecord;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Order;
use App\Models\SellerLedgerEntry;
use App\Models\SellerPayout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class SellerPayoutTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private Company $sellerCo;
    private User $seller;
    private User $otherSeller;
    private array $address = ['name' => 'Layla', 'phone' => '0501234567', 'line1' => '12 King Road', 'city' => 'Riyadh', 'country' => 'SA'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
        $store = Company::withoutGlobalScopes()->where('name', 'Store')->first();
        $store->update(['slug' => 'main-store', 'settings' => ['storefront' => ['enabled' => true]]]);
        $this->sellerCo = Company::create(['tenant_id' => $store->tenant_id, 'name' => 'Gulf Parts', 'type' => 'supplier', 'status' => 'active']);
        $this->seller = tap(User::factory()->create(['company_id' => $this->sellerCo->id]), fn ($u) => $u->assignRole('seller'));
        $rival = Company::create(['tenant_id' => $store->tenant_id, 'name' => 'Rival Parts', 'type' => 'supplier', 'status' => 'active']);
        $this->otherSeller = tap(User::factory()->create(['company_id' => $rival->id]), fn ($u) => $u->assignRole('seller'));
    }

    /** A sale of a seller's product: ordered, paid; optionally shipped and delivered. */
    private function sale(bool $deliver = false): int
    {
        $productId = $this->actingAs($this->seller, 'sanctum')->postJson('/api/products', ['name' => 'Brake pads', 'slug' => 'brake-pads-'.uniqid(), 'base_price' => 100, 'category' => 'brakes'])->assertCreated()->json('id');
        $this->putJson("/api/products/{$productId}/price-rules", ['rules' => [['target_type' => 'vehicle_part', 'target_id' => $this->hood->id, 'price' => 200, 'is_active' => true]]])->assertOk();

        $orderId = $this->actingAs($this->customer, 'sanctum')->postJson('/api/orders', ['product_id' => $productId, 'target_type' => 'vehicle_part', 'targets' => [$this->hood->id], 'shipping_address' => $this->address])
            ->assertCreated()->json('order.id');
        $this->postJson("/api/orders/{$orderId}/payments", ['amount' => 200])->assertCreated();

        if ($deliver) {
            $ship = $this->actingAs($this->seller, 'sanctum')->postJson("/api/orders/{$orderId}/shipments", ['provider' => 'manual', 'carrier' => 'SMSA', 'tracking_number' => 'T'.$orderId])->assertCreated()->json();
            $this->postJson("/api/shipments/{$ship['id']}/status", ['status' => 'delivered'])->assertOk();
        }

        return $orderId;
    }

    public function test_a_paid_sale_is_held_then_released_by_delivery(): void
    {
        $id = $this->sale();

        $entries = SellerLedgerEntry::where('order_id', $id)->get()->keyBy('type');
        $this->assertEquals(200, $entries['sale']->amount);
        $this->assertEquals(-20, $entries['commission']->amount);              // the platform's 10%
        $this->assertSame(['held', 'held'], $entries->pluck('status')->values()->all());

        $bal = $this->actingAs($this->seller, 'sanctum')->getJson('/api/seller/earnings')->assertOk()->json('balances');
        $this->assertEquals(180, $bal['USD']['held']);
        $this->assertEquals(0, $bal['USD']['available']);                       // nothing can be paid before the parcel arrives

        $ship = $this->postJson("/api/orders/{$id}/shipments", ['provider' => 'manual', 'carrier' => 'SMSA', 'tracking_number' => 'T1'])->assertCreated()->json();
        $this->postJson("/api/shipments/{$ship['id']}/status", ['status' => 'in_transit'])->assertOk();
        $this->assertSame('held', SellerLedgerEntry::where('order_id', $id)->first()->status);   // in transit is not delivered yet

        $this->postJson("/api/shipments/{$ship['id']}/status", ['status' => 'delivered'])->assertOk();
        $bal = $this->getJson('/api/seller/earnings')->json('balances');
        $this->assertEquals(180, $bal['USD']['available']);
        $this->assertEquals(0, $bal['USD']['held']);
    }

    public function test_service_jobs_create_no_ledger_and_replaying_events_never_duplicates(): void
    {
        $this->order();                                                          // a normal partner service job
        $this->assertSame(0, SellerLedgerEntry::count());

        $id = $this->sale(deliver: true);
        $paid = EventRecord::where('name', 'order.paid')->where('aggregate_id', (string) $id)->first();
        app(EventBus::class)->replay($paid->event_id);
        app(EventBus::class)->replay($paid->event_id);

        $this->assertSame(2, SellerLedgerEntry::where('order_id', $id)->count());   // still one sale and one commission
        $this->assertSame('available', SellerLedgerEntry::where('order_id', $id)->first()->status);   // a late replay does not re-hold released money
    }

    public function test_the_admin_pays_a_seller_out_and_the_seller_is_told(): void
    {
        $this->sale();                                                           // held: not payable
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/admin/payouts', ['company_id' => $this->sellerCo->id, 'currency' => 'USD'])->assertUnprocessable();

        $this->sale(deliver: true);
        $this->actingAs($this->admin, 'sanctum');
        $overview = $this->getJson('/api/admin/payouts')->assertOk()->json('sellers.0');
        $this->assertSame('Gulf Parts', $overview['name']);
        $this->assertEquals(180, $overview['balances']['USD']['available']);
        $this->assertEquals(180, $overview['balances']['USD']['held']);

        $payout = $this->postJson('/api/admin/payouts', ['company_id' => $this->sellerCo->id, 'currency' => 'usd'])->assertCreated()->assertJsonPath('status', 'pending')->json();
        $this->assertEquals(180, $payout['amount']);
        $this->postJson('/api/admin/payouts', ['company_id' => $this->sellerCo->id, 'currency' => 'USD'])->assertUnprocessable();   // already gathered

        $bal = $this->actingAs($this->seller, 'sanctum')->getJson('/api/seller/earnings')->json('balances');
        $this->assertEquals(180, $bal['USD']['in_payout']);
        $this->assertEquals(0, $bal['USD']['available']);

        $this->actingAs($this->admin, 'sanctum')->postJson("/api/admin/payouts/{$payout['id']}/paid")->assertUnprocessable();            // a reference is required
        $this->postJson("/api/admin/payouts/{$payout['id']}/paid", ['reference' => 'BANK-7788'])->assertOk()->assertJsonPath('status', 'paid');
        $this->postJson("/api/admin/payouts/{$payout['id']}/paid", ['reference' => 'AGAIN'])->assertUnprocessable();                   // only once

        $earn = $this->actingAs($this->seller, 'sanctum')->getJson('/api/seller/earnings')->assertOk();
        $this->assertEquals(180, $earn->json('balances.USD.paid'));
        $this->assertSame('BANK-7788', $earn->json('payouts.0.reference'));
        $this->assertTrue($this->seller->notifications()->where('data->kind', 'payout_paid')->exists());
        $this->assertSame(['payout.created', 'payout.paid'], EventRecord::where('name', 'like', 'payout.%')->orderBy('id')->pluck('name')->all());
        $this->assertSame(2, AuditLog::whereIn('action', ['payout.created', 'payout.paid'])->count());
        $this->assertSame(SellerPayout::first()->id, SellerLedgerEntry::where('status', 'paid')->first()->payout_id);
    }

    public function test_money_is_only_visible_to_those_it_belongs_to(): void
    {
        $this->sale(deliver: true);

        foreach ([$this->customer, $this->manager, $this->tech] as $nobody) {
            $this->actingAs($nobody, 'sanctum')->getJson('/api/admin/payouts')->assertForbidden();
            $this->postJson('/api/admin/payouts', ['company_id' => $this->sellerCo->id, 'currency' => 'USD'])->assertForbidden();
            $this->getJson('/api/seller/earnings')->assertForbidden();
        }
        // A seller cannot run payouts, and sees only their own money.
        $this->actingAs($this->seller, 'sanctum')->postJson('/api/admin/payouts', ['company_id' => $this->sellerCo->id, 'currency' => 'USD'])->assertForbidden();
        $this->actingAs($this->otherSeller, 'sanctum')->getJson('/api/seller/earnings')->assertOk()->assertJsonCount(0, 'entries');
        $this->assertEquals([], (array) $this->getJson('/api/seller/earnings')->json('balances'));
    }
}
