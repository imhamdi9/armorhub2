<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Order;
use App\Models\PayoutAccount;
use App\Models\Shipment;
use App\Models\User;
use App\Support\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** A seller says where to be paid, ArmorHub checks it, and the seller asks for the money once enough is available. */
class SellerPayoutRequestTest extends TestCase
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
        $co = Company::create(['tenant_id' => $this->customer->company->tenant_id, 'name' => 'Gulf Parts', 'type' => 'supplier', 'status' => 'active']);
        $this->seller = User::factory()->create(['email' => 'a@sellers.example.org', 'company_id' => $co->id]);
        $this->seller->assignRole('seller');
        $this->productId = $this->actingAs($this->seller, 'sanctum')->postJson('/api/products', ['name' => 'Brake pads', 'slug' => 'bp-pay', 'base_price' => 100, 'category' => 'spare-parts', 'requires_shipping' => true])->json('id');
        $this->putJson("/api/products/{$this->productId}/price-rules", ['rules' => [['target_type' => 'vehicle_part', 'target_id' => $this->hood->id, 'price' => 100, 'is_active' => true]]])->assertOk();
    }

    private function deliveredSale(): Order
    {
        $this->actingAs($this->customer, 'sanctum')->postJson('/api/cart/items', ['product_id' => $this->productId])->assertCreated();
        $g = $this->postJson('/api/checkout', ['shipping_address' => $this->address])->assertCreated()->json();
        $this->postJson("/api/order-groups/{$g['id']}/payments", [])->assertOk();
        $o = Order::withoutGlobalScopes()->where('group_id', $g['id'])->firstOrFail();
        $ship = $this->actingAs($this->seller, 'sanctum')->postJson("/api/orders/{$o->id}/shipments", ['provider' => 'manual', 'carrier' => 'SMSA', 'tracking_number' => 'P'.$o->id])->assertCreated()->json();
        $this->postJson("/api/shipments/{$ship['id']}/status", ['status' => 'delivered', 'code' => Shipment::find($ship['id'])->delivery_code])->assertOk();

        return $o;
    }

    public function test_the_account_is_checked_before_money_can_be_requested_and_the_minimum_applies(): void
    {
        $this->actingAs($this->seller, 'sanctum');
        $this->postJson('/api/seller/payouts/request', ['currency' => 'USD'])->assertUnprocessable()->assertJsonValidationErrors('account');   // no account yet

        $this->putJson('/api/seller/payout-account', ['method' => 'bank', 'holder' => 'Gulf Parts LLC', 'bank_name' => 'Nile Bank', 'account_number' => 'bad'])->assertUnprocessable();
        $saved = $this->putJson('/api/seller/payout-account', ['method' => 'bank', 'holder' => 'Gulf Parts LLC', 'bank_name' => 'Nile Bank', 'account_number' => 'EG38 0019 0005 0000 0000 2631 8000 2'])->assertOk()->json('account');
        $this->assertSame('pending', $saved['status']);
        $this->assertStringEndsWith('0002', $saved['number']);
        $this->assertStringNotContainsString('EG38', json_encode($saved));                                              // the seller sees it masked
        $this->assertStringNotContainsString('EG38', PayoutAccount::first()->getRawOriginal('account_number'));          // and it is stored encrypted

        $this->postJson('/api/seller/payouts/request', ['currency' => 'USD'])->assertUnprocessable()->assertJsonValidationErrors('account');   // not verified yet

        // The admin sees the full number and verifies it.
        $this->actingAs($this->customer, 'sanctum')->getJson('/api/admin/payout-accounts')->assertForbidden();
        $row = $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/payout-accounts')->assertOk()->json('0');
        $this->assertSame('EG380019000500000000263180002', $row['number']);
        $this->postJson("/api/admin/payout-accounts/{$row['id']}/decide", ['decision' => 'rejected'])->assertUnprocessable();    // a reason is needed
        $this->postJson("/api/admin/payout-accounts/{$row['id']}/decide", ['decision' => 'verified'])->assertOk();
        $this->assertTrue($this->seller->notifications()->get()->contains(fn ($n) => str_contains($n->data['title'] ?? '', 'verified')));

        // Verified, but nothing earned yet, then below the minimum, then enough.
        $this->actingAs($this->seller, 'sanctum')->postJson('/api/seller/payouts/request', ['currency' => 'USD'])->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->deliveredSale();
        app(Settings::class)->set('payouts.min_amount', 500);
        $this->postJson('/api/seller/payouts/request', ['currency' => 'USD'])->assertUnprocessable()->assertJsonValidationErrors('amount');
        app(Settings::class)->set('payouts.min_amount', 50);
        $p = $this->postJson('/api/seller/payouts/request', ['currency' => 'USD'])->assertCreated()->json();
        $this->assertSame('seller', $p['source']);

        // The person who pays sees where it goes (even if the seller later changes the account), and the seller does not see the number.
        $listed = collect($this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/payouts')->json('payouts'))->firstWhere('id', $p['id']);
        $this->assertSame('EG380019000500000000263180002', $listed['account']['number']);
        $this->assertArrayNotHasKey('account', $this->actingAs($this->seller, 'sanctum')->getJson('/api/seller/earnings')->json('payouts.0'));
        $this->postJson('/api/seller/payouts/request', ['currency' => 'USD'])->assertUnprocessable();                    // everything is already in that payout

        // Changing the details sends them for checking again.
        $this->putJson('/api/seller/payout-account', ['method' => 'wallet', 'holder' => 'Gulf Parts LLC', 'wallet_phone' => '+20 100 123 4567'])->assertOk()->assertJsonPath('account.status', 'pending');
    }

    public function test_only_sellers_use_it(): void
    {
        $this->actingAs($this->customer, 'sanctum')->getJson('/api/seller/payout-account')->assertForbidden();
        $this->putJson('/api/seller/payout-account', ['method' => 'wallet', 'holder' => 'X Y Z', 'wallet_phone' => '0100123456'])->assertForbidden();
    }
}
