<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Industry;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductPriceRule;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Markets\Markets;
use App\Support\Settings\Settings;
use Database\Seeders\IndustrySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IndustrySeeder::class, RolesAndPermissionsSeeder::class]);
    }

    private function markets(): Markets
    {
        return app(Markets::class);
    }

    private function store(string $name, ?string $market): Company
    {
        $tenant = Tenant::create(['name' => $name, 'industry_id' => Industry::where('key', 'automotive')->value('id'), 'market' => $market]);

        return Company::create(['tenant_id' => $tenant->id, 'name' => $name, 'status' => 'active',
            'settings' => ['storefront' => ['enabled' => true]]]);
    }

    public function test_vat_is_extracted_from_inclusive_prices_per_country(): void
    {
        $sa = $this->markets()->get('SA')->vat(115.0);
        $this->assertSame(['rate' => 15.0, 'net' => 100.0, 'tax' => 15.0, 'gross' => 115.0], $sa);

        $eg = $this->markets()->get('EG')->vat(114.0);
        $this->assertSame(100.0, $eg['net']);
        $this->assertSame(14.0, $eg['tax']);
    }

    public function test_phone_numbers_are_normalised_and_validated_per_country(): void
    {
        $sa = $this->markets()->get('SA');
        foreach (['0501234567', '501234567', '+966 50 123 4567', '00966501234567', '٠٥٠١٢٣٤٥٦٧'] as $input) {
            $this->assertSame('+966501234567', $sa->normalizePhone($input), $input);
        }
        $this->assertNull($sa->normalizePhone('0123456789'));
        $this->assertNull($sa->normalizePhone('12345'));

        $eg = $this->markets()->get('EG');
        $this->assertSame('+201012345678', $eg->normalizePhone('01012345678'));
        $this->assertNull($eg->normalizePhone('0501234567'));
    }

    public function test_tax_registration_numbers_are_checked_for_shape(): void
    {
        $this->assertTrue($this->markets()->get('SA')->validTaxId('300000000000003'));
        $this->assertFalse($this->markets()->get('SA')->validTaxId('12345'));
        $this->assertTrue($this->markets()->get('EG')->validTaxId('123456789'));
    }

    public function test_without_a_market_the_single_global_currency_still_applies(): void
    {
        $company = $this->store('Plain', null);
        $this->assertNull($this->markets()->forCompany($company));
        $this->assertSame('USD', $this->markets()->currencyFor($company));
    }

    public function test_a_tenant_market_decides_currency_and_the_default_fills_the_gaps(): void
    {
        $eg = $this->store('Cairo', 'EG');
        $none = $this->store('Nowhere', null);

        $this->assertSame('EGP', $this->markets()->currencyFor($eg));
        $this->assertSame('USD', $this->markets()->currencyFor($none));

        app(Settings::class)->set('platform.default_market', 'SA');
        $this->assertSame('SAR', $this->markets()->currencyFor($none));
        $this->assertSame('EGP', $this->markets()->currencyFor($eg));
    }

    public function test_public_markets_and_storefronts_carry_currency_and_vat(): void
    {
        $sa = $this->store('Riyadh Shield', 'SA');
        $sa->update(['slug' => 'riyadh-shield']);
        Product::create(['industry_id' => $sa->tenant->industry_id, 'name' => 'Film', 'slug' => 'film', 'base_price' => 100, 'is_active' => true, 'category' => 'ppf']);

        $list = $this->getJson('/api/public/markets')->assertOk()->json('markets');
        $this->assertEqualsCanonicalizing(['SA', 'EG'], array_column($list, 'code'));
        $this->assertSame('ر.س', collect($list)->firstWhere('code', 'SA')['currency_symbol']['ar']);

        $show = $this->getJson('/api/public/storefronts/riyadh-shield')->assertOk()->json();
        $this->assertSame('SAR', $show['featured'][0]['currency']);
        $this->assertEquals(15, $show['market']['vat_rate']);
    }

    public function test_products_can_be_limited_to_one_country(): void
    {
        $sa = $this->store('Riyadh Shield', 'SA');
        $sa->update(['slug' => 'riyadh-shield']);
        $eg = $this->store('Cairo Shield', 'EG');
        $eg->update(['slug' => 'cairo-shield']);
        $industry = $sa->tenant->industry_id;
        Product::create(['industry_id' => $industry, 'market' => 'SA', 'name' => 'Saudi film', 'slug' => 'a', 'base_price' => 500, 'is_active' => true]);
        Product::create(['industry_id' => $industry, 'market' => 'EG', 'name' => 'Egypt film', 'slug' => 'b', 'base_price' => 5000, 'is_active' => true]);
        Product::create(['industry_id' => $industry, 'market' => null, 'name' => 'Everywhere film', 'slug' => 'c', 'base_price' => 1, 'is_active' => true]);

        $names = fn ($slug) => collect($this->getJson("/api/public/storefronts/$slug/products")->json('data'))->pluck('name')->sort()->values()->all();

        $this->assertSame(['Everywhere film', 'Saudi film'], $names('riyadh-shield'));
        $this->assertSame(['Egypt film', 'Everywhere film'], $names('cairo-shield'));
    }

    public function test_signup_checks_the_phone_against_the_stores_country(): void
    {
        $eg = $this->store('Cairo Shield', 'EG');
        $eg->update(['slug' => 'cairo-shield']);
        $signup = function (array $extra) {
            $challenge = $this->getJson('/api/signup-challenge')->json();
            $this->travel(5)->seconds();

            return $this->postJson('/api/register', $extra + ['name' => 'Mona', 'email' => 'mona@example.org', 'signup_token' => $challenge['token'],
                'password' => 'password123', 'password_confirmation' => 'password123', 'store' => 'cairo-shield']);
        };

        $signup(['phone' => '0501234567'])->assertUnprocessable()->assertJsonValidationErrors('phone');

        $signup(['phone' => '010 1234 5678'])->assertCreated();
        $this->assertSame('+201012345678', User::where('email', 'mona@example.org')->value('phone'));
    }

    public function test_only_known_countries_can_be_assigned_to_a_tenant(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('platform-admin');
        $industry = Industry::first()->id;
        $this->actingAs($admin, 'sanctum');

        $this->postJson('/api/tenants', ['name' => 'X', 'industry_id' => $industry, 'market' => 'ZZ'])->assertUnprocessable();
        $id = $this->postJson('/api/tenants', ['name' => 'X', 'industry_id' => $industry, 'market' => 'EG'])->assertCreated()->json('id');
        $this->assertSame('EG', Tenant::find($id)->market);
    }

    public function test_tax_for_a_company_is_recorded_from_its_market(): void
    {
        $sa = $this->store('Riyadh Shield', 'SA');
        $this->assertEquals(['market' => 'SA', 'tax_rate' => 15, 'tax_amount' => 15.65], $this->markets()->taxFor($sa, 120.0));

        $none = $this->store('Plain', null);
        $this->assertEquals(['market' => null, 'tax_rate' => 0, 'tax_amount' => 0], $this->markets()->taxFor($none, 120.0));
    }
}
