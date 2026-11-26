<?php

namespace Tests\Feature;

use App\Models\Industry;
use App\Models\Product;
use App\Models\ProductPriceRule;
use App\Models\Tenant;
use App\Modules\Automotive\AutomotiveModule;
use App\Modules\Automotive\Models\VehicleModel;
use App\Modules\Automotive\Models\VehiclePart;
use App\Modules\Automotive\Models\VehicleMake;
use App\Modules\Contracts\BaseIndustryModule;
use App\Modules\IndustryRegistry;
use App\Services\Pricing\CalculateProductPriceService;
use Database\Seeders\IndustrySeeder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndustryModulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_automotive_module_is_registered_with_morph_aliases(): void
    {
        $registry = app(IndustryRegistry::class);

        $this->assertTrue($registry->has('automotive'));
        $this->assertInstanceOf(AutomotiveModule::class, $registry->get('automotive'));
        $this->assertSame(VehicleModel::class, Relation::getMorphedModel('vehicle_model'));
        $this->assertSame(VehiclePart::class, Relation::getMorphedModel('vehicle_part'));
    }

    public function test_unknown_module_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(IndustryRegistry::class)->get('nope');
    }

    public function test_seeder_creates_one_industry_per_module_and_is_idempotent(): void
    {
        $this->seed(IndustrySeeder::class);
        $this->seed(IndustrySeeder::class);

        $this->assertSame(1, Industry::where('key', 'automotive')->count());
        $this->assertInstanceOf(
            AutomotiveModule::class,
            Industry::where('key', 'automotive')->first()->module()
        );
    }

    public function test_tenant_belongs_to_an_industry(): void
    {
        $industry = Industry::create(['key' => 'legal', 'name' => 'Legal']);
        $tenant = Tenant::create(['name' => 'Law Group', 'industry_id' => $industry->id]);

        $this->assertTrue($tenant->industry->is($industry));
        // An industry without a code module is still valid (generic core).
        $this->assertNull($industry->module());
    }

    public function test_a_new_module_can_be_registered_without_touching_core(): void
    {
        $module = new class extends BaseIndustryModule {
            public function key(): string { return 'home-services'; }
            public function name(): string { return 'Home Services'; }
        };

        $registry = app(IndustryRegistry::class);
        $registry->register($module);

        $this->assertSame($module, $registry->get('home-services'));
        $this->assertArrayHasKey('home-services', $registry->all());
    }

    public function test_generic_pricing_sums_matching_rules_for_the_target_type(): void
    {
        $this->seed(IndustrySeeder::class);

        $product = Product::create([
            'industry_id' => Industry::where('key', 'automotive')->value('id'),
            'name' => 'Film',
            'slug' => 'film',
            'base_price' => 100,
            'attributes' => ['thickness_mil' => 8],
        ]);
        $this->assertSame(8, $product->fresh()->attributes['thickness_mil']);

        $hood = VehiclePart::create(['name' => 'Hood', 'slug' => 'hood']);
        $roof = VehiclePart::create(['name' => 'Roof', 'slug' => 'roof']);

        foreach ([[$hood, 1200], [$roof, 800]] as [$part, $price]) {
            ProductPriceRule::create([
                'product_id' => $product->id,
                'target_type' => 'vehicle_part',
                'target_id' => $part->id,
                'price' => $price,
            ]);
        }

        // A rule for another target type with the same id must not be counted.
        ProductPriceRule::create([
            'product_id' => $product->id,
            'target_type' => 'room',
            'target_id' => $hood->id,
            'price' => 9999,
        ]);

        $result = app(CalculateProductPriceService::class)
            ->calculate($product->id, [$hood->id, $roof->id], 'vehicle_part');

        $this->assertEquals(2000, $result['total']);
        $this->assertSame(2, $result['parts_count']);
    }

    public function test_order_subject_is_polymorphic(): void
    {
        $this->seed(IndustrySeeder::class);

        $make = VehicleMake::create(['name' => 'BMW', 'slug' => 'bmw']);
        $model = VehicleModel::create(['vehicle_make_id' => $make->id, 'name' => 'M4', 'slug' => 'm4']);
        $product = Product::create(['name' => 'Film', 'slug' => 'f', 'base_price' => 1]);

        $company = \App\Models\Company::create(['tenant_id' => Tenant::create(['name' => 'T'])->id, 'name' => 'Acme']);

        $order = \App\Models\Order::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'product_id' => $product->id,
            'subject_type' => 'vehicle_model',
            'subject_id' => $model->id,
        ]);

        $this->assertTrue($order->fresh()->subject->is($model));

        // Industries with nothing to service (e.g. a salon) need no subject.
        $noSubject = \App\Models\Order::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'product_id' => $product->id,
        ]);
        $this->assertNull($noSubject->fresh()->subject);
    }

    public function test_all_shipped_industries_are_registered_with_distinct_aliases(): void
    {
        $registry = app(IndustryRegistry::class);

        foreach (['automotive', 'home-services', 'beauty', 'devices'] as $key) {
            $this->assertTrue($registry->has($key), $key);
        }

        $this->assertSame(\App\Modules\HomeServices\Models\Property::class, Relation::getMorphedModel('property'));
        $this->assertSame(\App\Modules\Devices\Models\DeviceModel::class, Relation::getMorphedModel('device_model'));
        $this->assertSame(\App\Modules\Beauty\Models\TreatmentArea::class, Relation::getMorphedModel('treatment_area'));

        // Beauty has no customer-side subject.
        $this->assertSame([], $registry->get('beauty')->subjectModels());
    }

    public function test_industries_endpoint_lists_modules_for_authenticated_users(): void
    {
        $this->seed(IndustrySeeder::class);

        $this->getJson('/api/industries')->assertUnauthorized();

        $user = \App\Models\User::factory()->create();
        $response = $this->actingAs($user, 'sanctum')->getJson('/api/industries')->assertOk();

        $keys = collect($response->json())->pluck('key')->all();
        $this->assertEqualsCanonicalizing(['automotive', 'home-services', 'beauty', 'devices'], $keys);

        $auto = collect($response->json())->firstWhere('key', 'automotive');
        $this->assertSame(['vehicle_model'], $auto['subjects']);
        $this->assertSame(['vehicle_part'], $auto['targets']);
    }

    public function test_pricing_works_for_a_non_automotive_industry(): void
    {
        $this->seed(IndustrySeeder::class);
        $this->seed(\Database\Seeders\IndustryCatalogSeeder::class);

        $industry = Industry::where('key', 'beauty')->first();
        $product = Product::create([
            'industry_id' => $industry->id,
            'category' => 'facial',
            'name' => 'Glow Facial',
            'slug' => 'glow-facial',
            'base_price' => 50,
        ]);

        $face = \App\Modules\Beauty\Models\TreatmentArea::where('slug', 'face')->first();
        $hands = \App\Modules\Beauty\Models\TreatmentArea::where('slug', 'hands')->first();

        foreach ([[$face, 80], [$hands, 30]] as [$area, $price]) {
            ProductPriceRule::create([
                'product_id' => $product->id,
                'target_type' => 'treatment_area',
                'target_id' => $area->id,
                'price' => $price,
            ]);
        }

        $result = app(CalculateProductPriceService::class)
            ->calculate($product->id, [$face->id, $hands->id], 'treatment_area');

        $this->assertEquals(110, $result['total']);
    }
}
