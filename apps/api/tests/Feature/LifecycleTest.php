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
use App\Models\Warranty;
use App\Modules\Automotive\Models\VehiclePart;
use Database\Seeders\IndustrySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private User $manager;
    private User $tech;
    private User $rival;
    private User $admin;
    private Partner $shop;
    private Product $product;
    private VehiclePart $hood;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IndustrySeeder::class, RolesAndPermissionsSeeder::class]);

        $tenant = Tenant::create(['name' => 'T', 'industry_id' => Industry::where('key', 'automotive')->value('id')]);
        $store = Company::create(['tenant_id' => $tenant->id, 'name' => 'Store']);
        $shopCo = Company::create(['tenant_id' => $tenant->id, 'name' => 'ShopCo']);

        $this->shop = Partner::create([
            'company_id' => $shopCo->id, 'name' => 'Shop', 'city' => 'Riyadh', 'country' => 'SA',
            'latitude' => 24.7, 'longitude' => 46.7, 'location_enabled' => true,
        ]);
        $rivalShop = Partner::create(['name' => 'Rival', 'city' => 'Riyadh', 'country' => 'SA']);

        $mk = fn (string $role, $company, $partner = null) => tap(User::factory()->create([
            'company_id' => $company?->id, 'partner_id' => $partner?->id,
            'city' => 'Riyadh', 'country' => 'SA', 'latitude' => 24.71, 'longitude' => 46.68, 'location_enabled' => true,
        ]), fn ($u) => $u->assignRole($role));

        $this->customer = $mk('customer', $store);
        $this->manager = $mk('partner-manager', $shopCo, $this->shop);
        $this->tech = $mk('technician', $shopCo, $this->shop);
        $this->rival = $mk('partner-manager', null, $rivalShop);
        $this->admin = $mk('platform-admin', null);

        $this->product = Product::create([
            'industry_id' => $tenant->industry_id, 'name' => 'Film', 'slug' => 'film', 'base_price' => 1, 'warranty_years' => 5,
        ]);
        $this->hood = VehiclePart::create(['name' => 'Hood', 'slug' => 'hood']);
        ProductPriceRule::create([
            'product_id' => $this->product->id, 'target_type' => 'vehicle_part', 'target_id' => $this->hood->id, 'price' => 1000,
        ]);
    }

    /** Place an order and (optionally) take it all the way to paid. */
    private function order(bool $paid = true): int
    {
        $id = $this->actingAs($this->customer, 'sanctum')->postJson('/api/orders', [
            'product_id' => $this->product->id, 'target_type' => 'vehicle_part', 'targets' => [$this->hood->id],
        ])->assertCreated()->json('order.id');

        if ($paid) {
            $quote = PartnerQuote::withoutGlobalScopes()->where('order_id', $id)->where('partner_id', $this->shop->id)->first();
            $this->actingAs($this->manager, 'sanctum')->patchJson("/api/partner-quotes/{$quote->id}", ['price' => 200])->assertOk();
            $this->actingAs($this->customer, 'sanctum')->patchJson("/api/partner-quotes/{$quote->id}/accept")->assertOk();
            $this->postJson("/api/orders/{$id}/payments", ['amount' => 1200])->assertCreated();
        }

        return $id;
    }

    public function test_full_job_lifecycle_with_timeline_and_warranty(): void
    {
        $id = $this->order();
        $when = now()->addDays(3)->toIso8601String();

        $this->actingAs($this->manager, 'sanctum')->getJson('/api/partner/jobs')
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.status', 'paid')->assertJsonPath('0.customer.name', $this->customer->name);

        // Technicians work the job too.
        $this->actingAs($this->tech, 'sanctum')->postJson("/api/orders/{$id}/schedule", ['scheduled_at' => $when])
            ->assertOk()->assertJsonPath('status', 'appointment_scheduled');
        // Rescheduling is allowed before work starts.
        $this->postJson("/api/orders/{$id}/schedule", ['scheduled_at' => now()->addDays(4)->toIso8601String()])->assertOk();

        $this->postJson("/api/orders/{$id}/start")->assertOk()->assertJsonPath('status', 'service_in_progress');
        $this->postJson("/api/orders/{$id}/schedule", ['scheduled_at' => $when])->assertUnprocessable();

        $this->actingAs($this->manager, 'sanctum')->postJson("/api/orders/{$id}/complete")
            ->assertOk()->assertJsonPath('status', 'completed');

        // The customer sees the whole story and a 5-year warranty.
        $order = $this->actingAs($this->customer, 'sanctum')->getJson("/api/orders/{$id}")->assertOk();
        $types = collect($order->json('events'))->pluck('type')->all();
        $this->assertSame(['created', 'quote_submitted', 'quote_accepted', 'paid', 'scheduled', 'scheduled', 'started', 'completed'], $types);
        $this->assertNotNull($order->json('warranty.ends_at'));

        $w = Warranty::withoutGlobalScopes()->first();
        $this->assertTrue($w->ends_at->between(now()->addYears(5)->subMinute(), now()->addYears(5)->addMinute()));
        $this->getJson('/api/warranties')->assertOk()->assertJsonCount(1)->assertJsonPath('0.active', true);
    }

    public function test_only_the_assigned_partners_staff_can_work_a_job(): void
    {
        $id = $this->order();
        $when = now()->addDay()->toIso8601String();

        foreach ([$this->rival, $this->customer] as $outsider) {
            $this->actingAs($outsider, 'sanctum')->postJson("/api/orders/{$id}/schedule", ['scheduled_at' => $when])->assertForbidden();
        }
        $this->actingAs($this->rival, 'sanctum')->getJson('/api/partner/jobs')->assertOk()->assertJsonCount(0);

        // Past dates are refused.
        $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/orders/{$id}/schedule", ['scheduled_at' => now()->subDay()->toIso8601String()])->assertUnprocessable();

        // Steps cannot be skipped.
        $this->postJson("/api/orders/{$id}/start")->assertUnprocessable();
        $this->postJson("/api/orders/{$id}/complete")->assertUnprocessable();
        $this->assertSame(OrderStatus::Paid, Order::withoutGlobalScopes()->find($id)->status);
    }

    public function test_customers_can_cancel_before_paying_but_not_after(): void
    {
        $open = $this->order(false);
        $stranger = User::factory()->create(['company_id' => $this->customer->company_id]);
        $stranger->assignRole('customer');

        $this->actingAs($stranger, 'sanctum')->postJson("/api/orders/{$open}/cancel")->assertForbidden();

        $this->actingAs($this->customer, 'sanctum')->postJson("/api/orders/{$open}/cancel", ['reason' => 'Changed my mind'])
            ->assertOk()->assertJsonPath('status', 'cancelled');

        // Partners stop seeing the request, and can no longer quote it.
        $quote = PartnerQuote::withoutGlobalScopes()->where('order_id', $open)->first();
        $this->actingAs($this->manager, 'sanctum')->getJson('/api/partner/orders')->assertOk()->assertJsonCount(0);
        $this->patchJson("/api/partner-quotes/{$quote->id}", ['price' => 5])->assertUnprocessable();
        $this->actingAs($this->customer, 'sanctum')->postJson("/api/orders/{$open}/cancel")->assertUnprocessable();

        // A paid order cannot be cancelled by the customer.
        $paid = $this->order();
        $this->postJson("/api/orders/{$paid}/cancel")->assertUnprocessable();
        $this->assertSame(OrderStatus::Paid, Order::withoutGlobalScopes()->find($paid)->status);
    }

    public function test_a_cancelled_order_cannot_be_paid_or_accepted(): void
    {
        $id = $this->order(false);
        $quote = PartnerQuote::withoutGlobalScopes()->where('order_id', $id)->first();
        $this->actingAs($this->manager, 'sanctum')->patchJson("/api/partner-quotes/{$quote->id}", ['price' => 100])->assertOk();

        $this->actingAs($this->customer, 'sanctum')->postJson("/api/orders/{$id}/cancel")->assertOk();
        $this->patchJson("/api/partner-quotes/{$quote->id}/accept")->assertUnprocessable();
        $this->postJson("/api/orders/{$id}/payments", ['amount' => 1000])->assertUnprocessable();
    }

    public function test_products_without_warranty_issue_none(): void
    {
        $this->product->update(['warranty_years' => null]);
        $id = $this->order();
        $this->actingAs($this->manager, 'sanctum');
        $this->postJson("/api/orders/{$id}/schedule", ['scheduled_at' => now()->addDay()->toIso8601String()])->assertOk();
        $this->postJson("/api/orders/{$id}/start")->assertOk();
        $this->postJson("/api/orders/{$id}/complete")->assertOk();

        $this->assertSame(0, Warranty::withoutGlobalScopes()->count());
    }
}
