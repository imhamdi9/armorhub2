<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Company;
use App\Models\Industry;
use App\Models\Order;
use App\Models\Partner;
use App\Models\PartnerQuote;
use App\Models\Product;
use App\Models\ProductPriceRule;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Automotive\Models\VehicleMake;
use App\Modules\Automotive\Models\VehicleModel;
use App\Modules\Automotive\Models\VehiclePart;
use Database\Seeders\IndustrySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The whole customer journey: order -> partner quotes -> accept -> pay -> commissions.
 */
class OrderFlowTest extends TestCase
{
    use RefreshDatabase;

    private Company $storefront;
    private Company $partnerCompany;
    private Partner $partner;
    private User $customer;
    private User $partnerUser;
    private User $admin;
    private Product $product;
    private VehiclePart $hood;
    private VehicleModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IndustrySeeder::class, RolesAndPermissionsSeeder::class]);

        $tenant = Tenant::create([
            'name' => 'Armor',
            'industry_id' => Industry::where('key', 'automotive')->value('id'),
        ]);
        $this->storefront = Company::create(['tenant_id' => $tenant->id, 'name' => 'Storefront']);
        // A different company, to prove nothing relies on sharing one.
        $this->partnerCompany = Company::create(['tenant_id' => $tenant->id, 'name' => 'Installers Inc']);

        $this->partner = Partner::create([
            'company_id' => $this->partnerCompany->id,
            'name' => 'Shop One', 'city' => 'Riyadh', 'country' => 'SA',
            'latitude' => 24.7, 'longitude' => 46.7, 'location_enabled' => true,
        ]);

        $this->customer = User::factory()->create([
            'company_id' => $this->storefront->id,
            'city' => 'Riyadh', 'country' => 'SA',
            'latitude' => 24.71, 'longitude' => 46.68, 'location_enabled' => true,
        ]);
        $this->customer->assignRole('customer');

        $this->partnerUser = User::factory()->create([
            'company_id' => $this->partnerCompany->id,
            'partner_id' => $this->partner->id,
        ]);
        $this->partnerUser->assignRole('partner-manager');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('platform-admin');

        $this->product = Product::create([
            'industry_id' => $tenant->industry_id, 'name' => 'Film', 'slug' => 'film', 'base_price' => 10,
        ]);
        $this->hood = VehiclePart::create(['name' => 'Hood', 'slug' => 'hood']);
        ProductPriceRule::create([
            'product_id' => $this->product->id, 'target_type' => 'vehicle_part',
            'target_id' => $this->hood->id, 'price' => 1000,
        ]);

        $make = VehicleMake::create(['name' => 'BMW', 'slug' => 'bmw']);
        $this->model = VehicleModel::create(['vehicle_make_id' => $make->id, 'name' => 'M4', 'slug' => 'm4']);
    }

    private function orderPayload(array $over = []): array
    {
        return $over + [
            'product_id' => $this->product->id,
            'subject_type' => 'vehicle_model',
            'subject_id' => $this->model->id,
            'target_type' => 'vehicle_part',
            'targets' => [$this->hood->id],
        ];
    }

    private function placeOrder(): int
    {
        return $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/orders', $this->orderPayload())
            ->assertCreated()
            ->assertJsonPath('partner_quotes', 1)
            ->json('order.id');
    }

    public function test_full_journey_from_order_to_commission_split(): void
    {
        $orderId = $this->placeOrder();

        $order = Order::withoutGlobalScopes()->find($orderId);
        $this->assertSame(OrderStatus::PendingQuotes, $order->status);
        $this->assertEquals(1000, $order->total_amount);
        $this->assertTrue($order->subject->is($this->model));

        // The partner (a different company) sees the request and quotes.
        $quoteId = $this->actingAs($this->partnerUser, 'sanctum')
            ->getJson('/api/partner/orders')->assertOk()->assertJsonCount(1)
            ->json('0.id');

        $this->patchJson("/api/partner-quotes/{$quoteId}", ['price' => 200])->assertOk();
        $this->assertSame(OrderStatus::QuotesReceived, $order->fresh()->status);

        // The customer sees the quote and accepts it.
        $this->actingAs($this->customer, 'sanctum')
            ->getJson("/api/orders/{$orderId}/partner-quotes")
            ->assertOk()->assertJsonPath('0.price', fn ($v) => $v == 200)->assertJsonPath('0.partner.name', 'Shop One');

        $this->patchJson("/api/partner-quotes/{$quoteId}/accept")
            ->assertOk()->assertJsonPath('order.status', 'partner_selected');
        $this->assertEquals(1200, $order->fresh()->total_amount);

        // Pay: exact amount only.
        $this->postJson("/api/orders/{$orderId}/payments", ['amount' => 5])->assertUnprocessable();
        $this->postJson("/api/orders/{$orderId}/payments", ['amount' => 1200, 'payment_method' => 'card'])
            ->assertCreated()
            ->assertJsonPath('order.status', 'paid');

        // No double payment.
        $this->postJson("/api/orders/{$orderId}/payments", ['amount' => 1200])->assertUnprocessable();
        $this->assertSame(1, \App\Models\Payment::withoutGlobalScopes()->count());

        // 10% commission = 120, split 35/25/40.
        $dist = \App\Models\CommissionDistribution::all()->pluck('amount', 'type');
        $this->assertEquals(42, $dist['platform']);
        $this->assertEquals(30, $dist['company']);
        $this->assertEquals(48, $dist['partner']);

        // The partner and its company see their own shares, never the platform's.
        $types = collect($this->actingAs($this->partnerUser, 'sanctum')
            ->getJson('/api/commission-distributions')->assertOk()->json('data'))->pluck('type')->sort()->values()->all();
        $this->assertSame(['company', 'partner'], $types);
        $this->getJson('/api/dashboard/earnings')->assertOk()
            ->assertJsonPath('partners', fn ($v) => $v == 48)
            ->assertJsonPath('companies', fn ($v) => $v == 30)
            ->assertJsonPath('platform', fn ($v) => $v == 0);

        // The customer sees none.
        $this->actingAs($this->customer, 'sanctum')->getJson('/api/commissions')->assertForbidden();

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/commission-distributions')->assertJsonCount(3, 'data');
    }

    public function test_order_is_not_created_when_it_cannot_be_fulfilled(): void
    {
        $this->actingAs($this->customer, 'sanctum');

        // No price rule for the selected target.
        $other = VehiclePart::create(['name' => 'Roof', 'slug' => 'roof']);
        $this->postJson('/api/orders', $this->orderPayload(['targets' => [$other->id]]))->assertUnprocessable();

        // No location.
        $this->customer->update(['location_enabled' => false]);
        $this->postJson('/api/orders', $this->orderPayload())->assertUnprocessable();

        // Unknown subject.
        $this->customer->update(['location_enabled' => true]);
        $this->postJson('/api/orders', $this->orderPayload(['subject_id' => 9999]))->assertUnprocessable();

        $this->assertSame(0, Order::withoutGlobalScopes()->count());
        $this->assertSame(0, PartnerQuote::withoutGlobalScopes()->count());
    }

    public function test_unauthenticated_requests_are_rejected_everywhere(): void
    {
        foreach ([
            ['patch', '/api/partner-quotes/1'],
            ['patch', '/api/partner-quotes/1/accept'],
            ['get', '/api/orders/1/partner-quotes'],
            ['post', '/api/orders/1/payments'],
            ['get', '/api/commissions'],
            ['get', '/api/commission-distributions'],
            ['patch', '/api/commission-distributions/1/pay'],
            ['get', '/api/dashboard/earnings'],
        ] as [$method, $url]) {
            $this->json($method, $url)->assertUnauthorized();
        }
    }

    public function test_only_the_owning_customer_can_accept_pay_or_view(): void
    {
        $orderId = $this->placeOrder();
        $quoteId = PartnerQuote::withoutGlobalScopes()->value('id');

        $this->actingAs($this->partnerUser, 'sanctum')
            ->patchJson("/api/partner-quotes/{$quoteId}", ['price' => 100])->assertOk();

        $stranger = User::factory()->create(['company_id' => $this->storefront->id]);
        $stranger->assignRole('customer');

        $this->actingAs($stranger, 'sanctum');
        $this->getJson("/api/orders/{$orderId}")->assertForbidden();
        $this->getJson("/api/orders/{$orderId}/partner-quotes")->assertForbidden();
        $this->patchJson("/api/partner-quotes/{$quoteId}/accept")->assertForbidden();
        $this->postJson("/api/orders/{$orderId}/payments", ['amount' => 1100])->assertForbidden();
    }

    public function test_partners_can_only_quote_their_own_requests(): void
    {
        $this->placeOrder();
        $quoteId = PartnerQuote::withoutGlobalScopes()->value('id');

        $otherPartner = Partner::create(['name' => 'Rival', 'city' => 'Riyadh', 'country' => 'SA']);
        $rival = User::factory()->create(['partner_id' => $otherPartner->id]);
        $rival->assignRole('partner-manager');

        $this->actingAs($rival, 'sanctum')
            ->patchJson("/api/partner-quotes/{$quoteId}", ['price' => 1])->assertForbidden();

        // A partner manager without a partner is refused, not crashed.
        $orphan = User::factory()->create();
        $orphan->assignRole('partner-manager');
        $this->actingAs($orphan, 'sanctum')
            ->patchJson("/api/partner-quotes/{$quoteId}", ['price' => 1])->assertForbidden();
    }

    public function test_cannot_accept_unsubmitted_or_a_second_quote_nor_pay_early(): void
    {
        $orderId = $this->placeOrder();
        $quoteId = PartnerQuote::withoutGlobalScopes()->value('id');

        $this->actingAs($this->customer, 'sanctum');
        // Quote still pending (no price submitted).
        $this->patchJson("/api/partner-quotes/{$quoteId}/accept")->assertUnprocessable();
        // Paying before choosing a partner.
        $this->postJson("/api/orders/{$orderId}/payments", ['amount' => 1000])->assertUnprocessable();

        $this->actingAs($this->partnerUser, 'sanctum')
            ->patchJson("/api/partner-quotes/{$quoteId}", ['price' => 100])->assertOk();
        $this->actingAs($this->customer, 'sanctum')
            ->patchJson("/api/partner-quotes/{$quoteId}/accept")->assertOk();
        // Already selected.
        $this->patchJson("/api/partner-quotes/{$quoteId}/accept")->assertUnprocessable();
    }

    public function test_only_admins_can_mark_commissions_paid(): void
    {
        $orderId = $this->placeOrder();
        $quoteId = PartnerQuote::withoutGlobalScopes()->value('id');
        $this->actingAs($this->partnerUser, 'sanctum')->patchJson("/api/partner-quotes/{$quoteId}", ['price' => 200]);
        $this->actingAs($this->customer, 'sanctum')->patchJson("/api/partner-quotes/{$quoteId}/accept");
        $this->postJson("/api/orders/{$orderId}/payments", ['amount' => 1200])->assertCreated();

        $id = \App\Models\CommissionDistribution::value('id');

        $this->actingAs($this->partnerUser, 'sanctum')
            ->patchJson("/api/commission-distributions/{$id}/pay")->assertForbidden();

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/commission-distributions/{$id}/pay")->assertOk();
        $this->patchJson("/api/commission-distributions/{$id}/pay")->assertUnprocessable();
    }

    public function test_registration_creates_a_customer_of_a_company(): void
    {
        $this->postJson('/api/register', [
            'name' => 'New Customer', 'email' => 'new@example.com',
            'password' => 'password123', 'password_confirmation' => 'password123',
            'company_id' => $this->storefront->id,
            'latitude' => 24.7, 'longitude' => 46.7,
        ])->assertCreated();

        $user = User::where('email', 'new@example.com')->first();
        $this->assertTrue($user->hasRole('customer'));
        $this->assertSame($this->storefront->id, $user->company_id);
        $this->assertTrue((bool) $user->location_enabled);

        $this->postJson('/api/register', [
            'name' => 'X', 'email' => 'x@example.com',
            'password' => 'password123', 'password_confirmation' => 'password123',
            'company_id' => 99999,
        ])->assertUnprocessable();
    }

    public function test_partner_crud(): void
    {
        $admin = $this->actingAs($this->admin, 'sanctum');

        $id = $admin->postJson('/api/partners', ['name' => 'New', 'city' => 'Jeddah', 'country' => 'SA'])
            ->assertCreated()->json('id');
        $this->putJson("/api/partners/{$id}", ['phone' => '123'])->assertOk()->assertJsonPath('phone', '123');
        $this->getJson("/api/partners/{$id}")->assertOk();
        $this->deleteJson("/api/partners/{$id}")->assertOk();

        $this->actingAs($this->customer, 'sanctum')->getJson('/api/partners')->assertForbidden();
    }

    public function test_customer_can_share_location(): void
    {
        $this->customer->update(['location_enabled' => false, 'latitude' => null, 'longitude' => null]);

        $this->actingAs($this->customer, 'sanctum')
            ->putJson('/api/me/location', ['latitude' => 24.7, 'longitude' => 46.7, 'city' => 'Riyadh'])
            ->assertOk()->assertJsonPath('city', 'Riyadh');

        $this->assertTrue((bool) $this->customer->fresh()->location_enabled);
        $this->putJson('/api/me/location', ['latitude' => 999, 'longitude' => 0])->assertUnprocessable();
    }

    public function test_ordering_for_a_customer_owned_item_enforces_ownership(): void
    {
        $roomType = \App\Modules\HomeServices\Models\RoomType::create(['name' => 'Kitchen', 'slug' => 'kitchen']);
        $cleaning = Product::create(['name' => 'Deep clean', 'slug' => 'deep-clean', 'base_price' => 1]);
        ProductPriceRule::create([
            'product_id' => $cleaning->id, 'target_type' => 'room_type',
            'target_id' => $roomType->id, 'price' => 80,
        ]);

        $this->actingAs($this->customer, 'sanctum');
        $mine = $this->postJson('/api/my-items/property', ['type' => 'villa', 'city' => 'Riyadh'])->assertCreated()->json('id');

        $other = User::factory()->create(['company_id' => $this->storefront->id]);
        $other->assignRole('customer');
        $theirs = \App\Modules\HomeServices\Models\Property::create(['customer_id' => $other->id, 'type' => 'flat']);

        $payload = fn ($propertyId) => [
            'product_id' => $cleaning->id, 'subject_type' => 'property', 'subject_id' => $propertyId,
            'target_type' => 'room_type', 'targets' => [$roomType->id],
        ];

        // Someone else's property is refused, with nothing created.
        $this->postJson('/api/orders', $payload($theirs->id))->assertUnprocessable();
        $this->assertSame(0, Order::withoutGlobalScopes()->count());

        // Their own works, and the order's subject is the property.
        $orderId = $this->postJson('/api/orders', $payload($mine))->assertCreated()->json('order.id');
        $this->getJson("/api/orders/{$orderId}")->assertOk()
            ->assertJsonPath('subject_type', 'property')
            ->assertJsonPath('subject.type', 'villa');
    }
}
