<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Industry;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\IndustryCatalogSeeder;
use Database\Seeders\IndustrySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IndustrySeeder::class, RolesAndPermissionsSeeder::class, IndustryCatalogSeeder::class]);
    }

    private function user(string $role, ?Company $company = null): User
    {
        $user = User::factory()->create(['company_id' => $company?->id]);
        $user->assignRole($role);

        return $user;
    }

    public function test_platform_admin_manages_products_and_price_rules(): void
    {
        $admin = $this->user('platform-admin');
        $beauty = Industry::where('key', 'beauty')->first();

        $id = $this->actingAs($admin, 'sanctum')->postJson('/api/products', [
            'industry_id' => $beauty->id,
            'category' => 'facial',
            'name' => 'Glow Facial',
            'slug' => 'glow-facial',
            'base_price' => 50,
            'attributes' => ['duration' => 45],
        ])->assertCreated()->json('id');

        $face = \App\Modules\Beauty\Models\TreatmentArea::where('slug', 'face')->value('id');

        $this->putJson("/api/products/{$id}/price-rules", ['rules' => [
            ['target_type' => 'treatment_area', 'target_id' => $face, 'price' => 80],
        ]])->assertOk()->assertJsonCount(1);

        // Unknown target types are rejected.
        $this->putJson("/api/products/{$id}/price-rules", ['rules' => [
            ['target_type' => 'bogus', 'target_id' => 1, 'price' => 1],
        ]])->assertUnprocessable();

        $this->postJson('/api/pricing/calculate', [
            'product_id' => $id, 'targets' => [$face], 'target_type' => 'treatment_area',
        ])->assertOk()->assertJsonPath('total', 80);

        $this->putJson("/api/products/{$id}", ['name' => 'Glow Facial+'])->assertOk()->assertJsonPath('name', 'Glow Facial+');
        $this->getJson('/api/products?category=facial')->assertOk()->assertJsonCount(1);
        $this->deleteJson("/api/products/{$id}")->assertOk();
        $this->assertSame(0, Product::count());
    }

    public function test_customers_can_read_but_not_write_products(): void
    {
        $customer = $this->user('customer');

        $this->actingAs($customer, 'sanctum')->getJson('/api/products')->assertOk();
        $this->postJson('/api/products', ['name' => 'x', 'slug' => 'x', 'base_price' => 1])->assertForbidden();
    }

    public function test_catalog_lists_active_records_by_alias(): void
    {
        $user = $this->user('customer');

        $rooms = $this->actingAs($user, 'sanctum')->getJson('/api/catalog/room_type')->assertOk();
        $this->assertContains('Kitchen', collect($rooms->json())->pluck('name')->all());

        $this->getJson('/api/catalog/nope')->assertNotFound();
        // Customer-owned subjects are not shared catalogs.
        $this->getJson('/api/catalog/property')->assertNotFound();
    }

    public function test_tenant_industry_is_exposed_on_me_and_admin_can_assign_it(): void
    {
        $admin = $this->user('platform-admin');
        $devices = Industry::where('key', 'devices')->first();

        $tenantId = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/tenants', ['name' => 'Fix It', 'industry_id' => $devices->id])
            ->assertCreated()->json('id');

        $company = Company::create(['tenant_id' => $tenantId, 'name' => 'Fix It HQ']);
        $owner = $this->user('company-owner', $company);

        $this->actingAs($owner, 'sanctum')->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('industry.key', 'devices')
            ->assertJsonPath('industry.subjects.0', 'device_model')
            ->assertJsonPath('industry.targets.0', 'device_component');

        // Only admins manage tenants.
        $this->putJson("/api/tenants/{$tenantId}", ['name' => 'Nope'])->assertForbidden();
    }

    public function test_me_has_null_industry_without_a_tenant(): void
    {
        $this->actingAs($this->user('customer'), 'sanctum')->getJson('/api/me')
            ->assertOk()->assertJsonPath('industry', null);
    }

    public function test_company_users_only_see_their_own_company(): void
    {
        $tenant = Tenant::create(['name' => 'T']);
        $mine = Company::create(['tenant_id' => $tenant->id, 'name' => 'Mine']);
        Company::create(['tenant_id' => Tenant::create(['name' => 'T2'])->id, 'name' => 'Other']);   // another market

        $owner = $this->user('company-owner', $mine);

        $this->actingAs($owner, 'sanctum')->getJson('/api/companies')
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.name', 'Mine');
    }

    public function test_catalog_crud_for_managers_only(): void
    {
        $admin = $this->user('platform-admin');

        $id = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/catalog/room_type', ['name' => 'Garage', 'slug' => 'garage'])
            ->assertCreated()->json('id');

        // Duplicate slug is rejected.
        $this->postJson('/api/catalog/room_type', ['name' => 'Garage 2', 'slug' => 'garage'])->assertUnprocessable();

        $this->putJson("/api/catalog/room_type/{$id}", ['name' => 'Car Garage'])->assertOk()->assertJsonPath('name', 'Car Garage');

        // Parent keys are required (device models need a brand).
        $this->postJson('/api/catalog/device_model', ['name' => 'X', 'slug' => 'x'])->assertUnprocessable();
        $brand = \App\Modules\Devices\Models\DeviceBrand::where('slug', 'apple')->value('id');
        $this->postJson('/api/catalog/device_model', ['name' => 'iPad', 'slug' => 'ipad', 'device_brand_id' => $brand])->assertCreated();

        $this->deleteJson("/api/catalog/room_type/{$id}")->assertOk();

        $this->actingAs($this->user('customer'), 'sanctum')
            ->postJson('/api/catalog/room_type', ['name' => 'Hack', 'slug' => 'hack'])->assertForbidden();
    }

    public function test_customers_manage_their_own_items(): void
    {
        $me = $this->user('customer');
        $other = $this->user('customer');

        $id = $this->actingAs($me, 'sanctum')
            ->postJson('/api/my-items/property', ['type' => 'villa', 'city' => 'Riyadh', 'area_sqm' => 250])
            ->assertCreated()->assertJsonPath('customer_id', $me->id)->json('id');

        $this->getJson('/api/my-items/property')->assertOk()->assertJsonCount(1);

        // Shared catalogs are not "my items", and others cannot touch mine.
        $this->getJson('/api/my-items/room_type')->assertNotFound();
        $this->actingAs($other, 'sanctum')->getJson('/api/my-items/property')->assertJsonCount(0);
        $this->deleteJson("/api/my-items/property/{$id}")->assertNotFound();

        $this->actingAs($me, 'sanctum')->deleteJson("/api/my-items/property/{$id}")->assertOk();
    }

    public function test_a_user_without_a_company_sees_no_company_data(): void
    {
        $tenant = Tenant::create(['name' => 'T']);
        Company::create(['tenant_id' => $tenant->id, 'name' => 'Secret Co']);

        // A company-owner who is not attached to any company must not see all companies.
        $stray = $this->user('company-owner');

        $this->actingAs($stray, 'sanctum')->getJson('/api/companies')->assertOk()->assertJsonCount(0);
    }

    public function test_inactive_products_are_hidden_from_customers(): void
    {
        Product::create(['name' => 'Live', 'slug' => 'live', 'base_price' => 1]);
        Product::create(['name' => 'Hidden', 'slug' => 'hidden', 'base_price' => 1, 'is_active' => false]);

        $customer = $this->actingAs($this->user('customer'), 'sanctum');
        $customer->getJson('/api/products?include_inactive=1')->assertOk()->assertJsonCount(1);

        $this->actingAs($this->user('platform-admin'), 'sanctum')
            ->getJson('/api/products?include_inactive=1')->assertOk()->assertJsonCount(2);
    }

    public function test_price_rules_must_point_at_existing_records(): void
    {
        $product = Product::create(['name' => 'P', 'slug' => 'p', 'base_price' => 1]);

        $this->actingAs($this->user('platform-admin'), 'sanctum')
            ->putJson("/api/products/{$product->id}/price-rules", ['rules' => [
                ['target_type' => 'room_type', 'target_id' => 99999, 'price' => 10],
            ]])->assertUnprocessable()->assertJsonValidationErrors('rules.0.target_id');
    }
}
