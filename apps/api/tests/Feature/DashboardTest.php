<?php

namespace Tests\Feature;

use App\Models\Industry;
use App\Models\Partner;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
    }

    private function cards($user): array
    {
        $json = $this->actingAs($user, 'sanctum')->getJson('/api/dashboard/stats')->assertOk()->json();

        return ['role' => $json['role']] + collect($json['cards'])->pluck('value', 'key')->all() + ['by_status' => $json['orders_by_status']];
    }

    public function test_each_role_gets_its_own_numbers(): void
    {
        $paid = $this->order();        // 1200 paid
        $open = $this->order(false);   // pending quotes

        $customer = $this->cards($this->customer);
        $this->assertSame('customer', $customer['role']);
        $this->assertEquals(2, $customer['orders']);
        $this->assertEquals(2, $customer['active']);
        $this->assertEquals(1200, $customer['spent']);
        $this->assertEquals(0, $customer['warranties']);
        $this->assertEquals(1, $customer['by_status']['paid']);

        $partner = $this->cards($this->manager);
        $this->assertSame('partner', $partner['role']);
        $this->assertEquals(1, $partner['open_requests']);   // the unpaid order still wants a quote
        $this->assertEquals(1, $partner['to_schedule']);
        $this->assertEquals(0, $partner['completed']);
        $this->assertEquals(48, $partner['earnings_pending']); // 40% of 120 commission

        $this->assertEquals(1, $this->cards($this->tech)['to_schedule']); // technicians see their partner's jobs

        // Finish the job and the numbers move.
        $this->actingAs($this->manager, 'sanctum');
        $this->postJson("/api/orders/{$paid}/schedule", ['scheduled_at' => now()->addDay()->toIso8601String()])->assertOk();
        $this->postJson("/api/orders/{$paid}/start")->assertOk();
        $this->postJson("/api/orders/{$paid}/complete")->assertOk();

        $this->assertEquals(1, $this->cards($this->manager)['completed']);
        $this->assertEquals(1, $this->cards($this->customer)['warranties']);

        $admin = $this->cards($this->admin);
        $this->assertSame('platform-admin', $admin['role']);
        $this->assertEquals(2, $admin['orders']);
        $this->assertEquals(1200, $admin['revenue']);
        $this->assertEquals(1, $admin['customers']);
        $this->assertEquals(2, $admin['partners']);
    }

    public function test_company_owner_sees_only_their_company(): void
    {
        $this->order();
        $owner = User::factory()->create(['company_id' => $this->customer->company_id]);
        $owner->assignRole('company-owner');
        // The owner of a company in ANOTHER market sees nothing of it.
        $foreign = \App\Models\Company::create(['tenant_id' => \App\Models\Tenant::create(['name' => 'Elsewhere'])->id, 'name' => 'Elsewhere Co']);
        $outsider = User::factory()->create(['company_id' => $foreign->id]);
        $outsider->assignRole('company-owner');

        $this->assertEquals(1, $this->cards($owner)['orders']);
        $this->assertEquals(1200, $this->cards($owner)['revenue']);
        $this->assertEquals(0, $this->cards($outsider)['orders']); // another company's owner sees nothing of it
    }

    public function test_orders_are_paginated_and_filterable(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->order(false);
        }
        $paid = $this->order();

        $this->actingAs($this->customer, 'sanctum');
        $page = $this->getJson('/api/orders?per_page=2')->assertOk();
        $page->assertJsonCount(2, 'data')->assertJsonPath('total', 4)->assertJsonPath('last_page', 2)
            ->assertJsonPath('data.0.id', $paid);

        $this->getJson('/api/orders?status=paid')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/orders?per_page=500')->assertUnprocessable();
    }

    public function test_search_for_products_and_partners(): void
    {
        Product::create(['name' => 'Ceramic Shield', 'brand' => 'Gtechniq', 'slug' => 'cs', 'base_price' => 1]);
        Product::create(['name' => 'Tint Pro', 'brand' => 'Llumar', 'slug' => 'tp', 'base_price' => 1]);

        $this->actingAs($this->admin, 'sanctum');
        $this->getJson('/api/products?search=ceramic')->assertJsonCount(1)->assertJsonPath('0.name', 'Ceramic Shield');
        $this->getJson('/api/products?search=llumar')->assertJsonCount(1)->assertJsonPath('0.name', 'Tint Pro');

        $this->getJson('/api/partners?search=rival')->assertJsonCount(1);
        $inactive = Partner::create(['name' => 'Closed', 'city' => 'X', 'country' => 'SA', 'is_active' => false]);
        $this->getJson('/api/partners')->assertJsonMissing(['id' => $inactive->id]);
        $this->getJson('/api/partners?include_inactive=1')->assertJsonFragment(['id' => $inactive->id]);
    }

    public function test_catalog_schema_and_manageable_catalogs(): void
    {
        $this->actingAs($this->admin, 'sanctum');

        // A vehicle model needs a vehicle make; vehicle makes are manageable in the automotive industry.
        $this->getJson('/api/catalog/vehicle_model/schema')->assertOk()
            ->assertJsonPath('parents.0', ['field' => 'vehicle_make_id', 'alias' => 'vehicle_make']);
        $this->getJson('/api/catalog/vehicle_part/schema')->assertJsonPath('parents', []);

        $make = $this->postJson('/api/catalog/vehicle_make', ['name' => 'Audi', 'slug' => 'audi'])->assertCreated()->json('id');
        $this->postJson('/api/catalog/vehicle_model', ['name' => 'A4', 'slug' => 'a4', 'vehicle_make_id' => $make])->assertCreated();

        // Customer-owned subjects are not managed as shared catalogs.
        $this->getJson('/api/catalog/property/schema')->assertNotFound();

        $tenant = Tenant::find($this->customer->company->tenant_id);
        $admin = User::factory()->create(['company_id' => $this->customer->company_id]);
        $admin->assignRole('company-owner');
        $me = $this->actingAs($admin, 'sanctum')->getJson('/api/me')->assertOk();
        $this->assertEqualsCanonicalizing(
            ['vehicle_part', 'vehicle_make', 'vehicle_model'],
            $me->json('industry.catalogs')
        );

        $devices = Industry::where('key', 'devices')->first();
        $tenant->update(['industry_id' => $devices->id]);
        $this->assertEqualsCanonicalizing(
            ['device_component', 'device_brand', 'device_model'],
            $this->getJson('/api/me')->json('industry.catalogs')
        );
    }

    public function test_products_and_partners_with_history_cannot_be_deleted(): void
    {
        $orderId = $this->order(false);

        $this->actingAs($this->admin, 'sanctum');
        $this->deleteJson("/api/products/{$this->product->id}")->assertUnprocessable();
        $this->deleteJson("/api/partners/{$this->shop->id}")->assertUnprocessable();

        // Nothing was lost.
        $this->assertNotNull(\App\Models\Order::withoutGlobalScopes()->find($orderId));
        $this->assertNotNull(Product::find($this->product->id));

        // Unused ones can go.
        $spare = Product::create(['name' => 'Spare', 'slug' => 'spare', 'base_price' => 1]);
        $this->deleteJson("/api/products/{$spare->id}")->assertOk();

        // Even a raw delete is blocked by the database.
        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->product->delete();
    }

    public function test_deleting_catalog_items_cleans_up_rules_and_protects_order_history(): void
    {
        $this->actingAs($this->admin, 'sanctum');

        // Price rules for a deleted target go with it.
        $this->assertSame(1, \App\Models\ProductPriceRule::count());
        $this->deleteJson("/api/catalog/vehicle_part/{$this->hood->id}")->assertOk();
        $this->assertSame(0, \App\Models\ProductPriceRule::count());

        // A vehicle model used by an order cannot be deleted.
        $make = \App\Modules\Automotive\Models\VehicleMake::create(['name' => 'BMW', 'slug' => 'bmw']);
        $model = \App\Modules\Automotive\Models\VehicleModel::create(['vehicle_make_id' => $make->id, 'name' => 'M4', 'slug' => 'm4']);
        $part = \App\Modules\Automotive\Models\VehiclePart::create(['name' => 'Roof', 'slug' => 'roof']);
        \App\Models\ProductPriceRule::create(['product_id' => $this->product->id, 'target_type' => 'vehicle_part', 'target_id' => $part->id, 'price' => 10]);
        $this->actingAs($this->customer, 'sanctum')->postJson('/api/orders', [
            'product_id' => $this->product->id, 'subject_type' => 'vehicle_model', 'subject_id' => $model->id,
            'target_type' => 'vehicle_part', 'targets' => [$part->id],
        ])->assertCreated();

        $this->actingAs($this->admin, 'sanctum')->deleteJson("/api/catalog/vehicle_model/{$model->id}")->assertUnprocessable();
        $this->assertNotNull(\App\Modules\Automotive\Models\VehicleModel::find($model->id));
    }
}
