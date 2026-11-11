<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Industry;
use App\Models\Partner;
use App\Models\Product;
use App\Models\Tenant;
use Database\Seeders\IndustrySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowcaseTest extends TestCase
{
    use RefreshDatabase;

    private function store(string $name, string $industryKey, string $market, bool $open = true): Company
    {
        $tenant = Tenant::create(['name' => $name, 'industry_id' => Industry::where('key', $industryKey)->value('id'), 'market' => $market]);
        $company = Company::create(['tenant_id' => $tenant->id, 'name' => $name, 'status' => 'active', 'settings' => ['storefront' => ['enabled' => $open]]]);
        Product::create(['industry_id' => $tenant->industry_id, 'name' => "$name product", 'slug' => str($name)->slug().'-p', 'base_price' => 100, 'is_active' => true]);

        return $company;
    }

    private function partner(Company $store, string $name, bool $public): Partner
    {
        $pc = Company::create(['tenant_id' => $store->tenant_id, 'name' => "$name co", 'type' => 'partner']);

        return Partner::create(['company_id' => $pc->id, 'name' => $name, 'city' => 'Riyadh', 'country' => 'SA', 'phone' => '+966500000000',
            'address' => '1 Secret Street', 'latitude' => 24.7, 'longitude' => 46.7, 'is_active' => true,
            'public_profile' => $public, 'description' => 'We do great work.']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IndustrySeeder::class);
    }

    public function test_the_showcase_groups_open_stores_by_industry_with_their_products(): void
    {
        $this->store('Car Shield', 'automotive', 'SA');
        $this->store('Home Hero', 'home-services', 'EG');
        $this->store('Closed Salon', 'beauty', 'SA', open: false);

        $industries = $this->getJson('/api/public/showcase')->assertOk()->assertHeader('Cache-Control', 'max-age=60, public, s-maxage=60')->json('industries');

        $keys = array_column(array_column($industries, 'industry'), 'key');
        $this->assertEqualsCanonicalizing(['automotive', 'home-services'], $keys);

        $auto = collect($industries)->first(fn ($i) => $i['industry']['key'] === 'automotive');
        $this->assertSame('Car Shield', $auto['stores'][0]['name']);
        $this->assertSame('SAR', $auto['stores'][0]['products'][0]['currency']);
        $home = collect($industries)->first(fn ($i) => $i['industry']['key'] === 'home-services');
        $this->assertSame('EGP', $home['stores'][0]['products'][0]['currency']);
    }

    public function test_only_partners_who_opted_in_appear_and_only_with_public_fields(): void
    {
        $store = $this->store('Car Shield', 'automotive', 'SA');
        $this->partner($store, 'Visible Garage', true);
        $this->partner($store, 'Private Garage', false);
        $inactive = $this->partner($store, 'Closed Garage', true);
        $inactive->update(['is_active' => false]);

        $res = $this->getJson('/api/public/showcase')->assertOk();
        $partners = $res->json('industries.0.stores.0.partners');

        $this->assertSame(['Visible Garage'], array_column($partners, 'name'));
        $this->assertEqualsCanonicalizing(['name', 'city', 'country', 'description', 'logo_url'], array_keys($partners[0]));

        $body = $res->getContent();
        foreach (['+966500000000', 'Secret Street', '24.7', 'Private Garage', 'Closed Garage'] as $leak) {
            $this->assertStringNotContainsString($leak, $body, "leaked: $leak");
        }
    }

    public function test_partner_profile_fields_are_validated(): void
    {
        $store = $this->store('Car Shield', 'automotive', 'SA');
        $admin = \App\Models\User::factory()->create();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $admin->assignRole('platform-admin');
        $partner = $this->partner($store, 'Garage', false);
        $this->actingAs($admin, 'sanctum');

        $this->putJson("/api/partners/{$partner->id}", ['logo_url' => 'javascript:alert(1)'])->assertUnprocessable()->assertJsonValidationErrors('logo_url');
        $this->putJson("/api/partners/{$partner->id}", ['logo_url' => 'http://x.example/a.png'])->assertUnprocessable();
        $this->putJson("/api/partners/{$partner->id}", ['description' => str_repeat('x', 401)])->assertUnprocessable();
        $this->putJson("/api/partners/{$partner->id}", ['public_profile' => true, 'logo_url' => 'https://cdn.example.com/a.png'])->assertOk();
        $this->assertTrue($partner->fresh()->public_profile);
    }
}
