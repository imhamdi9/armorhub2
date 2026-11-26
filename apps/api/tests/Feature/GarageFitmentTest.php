<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CustomerVehicle;
use App\Models\Product;
use App\Models\User;
use App\Modules\Automotive\Models\VehicleMake;
use App\Modules\Automotive\Models\VehicleModel;
use App\Services\Fitment\FitmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** The customer's cars, and what fits them. */
class GarageFitmentTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private VehicleMake $toyota;
    private VehicleMake $bmw;
    private VehicleModel $camry;
    private VehicleModel $corolla;
    private VehicleModel $x5;
    private User $seller;
    private Company $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
        $this->store = Company::withoutGlobalScopes()->where('name', 'Store')->first();
        $this->store->update(['slug' => 'main-store', 'settings' => ['storefront' => ['enabled' => true]]]);

        $this->toyota = VehicleMake::create(['name' => 'Toyota', 'slug' => 'toyota', 'is_active' => true]);
        $this->bmw = VehicleMake::create(['name' => 'BMW', 'slug' => 'bmw', 'is_active' => true]);
        $this->camry = VehicleModel::create(['vehicle_make_id' => $this->toyota->id, 'name' => 'Camry', 'slug' => 'camry', 'is_active' => true]);
        $this->corolla = VehicleModel::create(['vehicle_make_id' => $this->toyota->id, 'name' => 'Corolla', 'slug' => 'corolla', 'is_active' => true]);
        $this->x5 = VehicleModel::create(['vehicle_make_id' => $this->bmw->id, 'name' => 'X5', 'slug' => 'x5', 'is_active' => true]);

        $co = Company::create(['tenant_id' => $this->store->tenant_id, 'name' => 'Gulf Parts', 'type' => 'supplier', 'status' => 'active']);
        $this->seller = User::factory()->create(['email' => 'g@sellers.example.org', 'company_id' => $co->id]);
        $this->seller->assignRole('seller');
    }

    private function part(string $name, array $extra = []): int
    {
        $id = $this->actingAs($this->seller, 'sanctum')->postJson('/api/products', $extra + ['name' => $name, 'slug' => str($name)->slug().'-x', 'base_price' => 100, 'category' => 'spare-parts'])->assertCreated()->json('id');
        $this->putJson("/api/products/{$id}/price-rules", ['rules' => [['target_type' => 'vehicle_part', 'target_id' => $this->hood->id, 'price' => 100, 'is_active' => true]]])->assertOk();

        return $id;
    }

    private function car(VehicleModel $model, int $year, array $extra = []): int
    {
        return $this->actingAs($this->customer, 'sanctum')->postJson('/api/garage', ['vehicle_model_id' => $model->id, 'year' => $year] + $extra)->assertCreated()->json('id');
    }

    public function test_a_customer_keeps_their_cars_and_the_first_is_the_default(): void
    {
        $a = $this->car($this->camry, 2020, ['nickname' => '<b>Daily</b>', 'color' => 'White']);
        $b = $this->car($this->x5, 2022);

        $cars = $this->getJson('/api/garage')->assertOk()->json();
        $this->assertSame(['Toyota Camry 2020', 'BMW X5 2022'], array_column($cars, 'label'));
        $this->assertTrue($cars[0]['is_default']);
        $this->assertSame('Daily', $cars[0]['nickname']);                      // markup stripped

        $this->putJson("/api/garage/{$b}", ['is_default' => true])->assertOk();
        $this->assertSame([$b, $a], array_column($this->getJson('/api/garage')->json(), 'id'));

        $this->deleteJson("/api/garage/{$b}")->assertOk();                      // the default goes: another takes over
        $this->assertTrue($this->getJson('/api/garage')->json('0.is_default'));
    }

    public function test_a_car_needs_a_real_model_a_sensible_year_and_a_well_formed_vin(): void
    {
        $this->actingAs($this->customer, 'sanctum');
        $this->postJson('/api/garage', ['vehicle_model_id' => 999999, 'year' => 2020])->assertUnprocessable();
        $this->postJson('/api/garage', ['vehicle_model_id' => $this->camry->id, 'year' => 1950])->assertUnprocessable();
        $this->postJson('/api/garage', ['vehicle_model_id' => $this->camry->id, 'year' => now()->year + 5])->assertUnprocessable();
        foreach (['TOO-SHORT', '1HGCM82633A00435I', '1HGCM82633A00435O', '1HGCM82633A0043512'] as $bad) {
            $this->postJson('/api/garage', ['vehicle_model_id' => $this->camry->id, 'year' => 2020, 'vin' => $bad])->assertUnprocessable();
        }

        $this->camry->update(['is_active' => false]);
        $this->postJson('/api/garage', ['vehicle_model_id' => $this->camry->id, 'year' => 2020])->assertUnprocessable();
    }

    public function test_the_vin_is_stored_encrypted_and_shown_only_masked(): void
    {
        $id = $this->car($this->camry, 2020, ['vin' => '1hgcm82633a004352']);

        $shown = $this->getJson('/api/garage')->json('0.vin');
        $this->assertSame('•••••••••••••4352', $shown);

        $raw = \Illuminate\Support\Facades\DB::table('customer_vehicles')->where('id', $id)->value('vin');
        $this->assertStringNotContainsString('1HGCM82633A004352', (string) $raw);
        $this->assertSame('1HGCM82633A004352', CustomerVehicle::find($id)->vin);     // normalised to upper case, readable only through the model
    }

    public function test_cars_are_private_and_limited(): void
    {
        $id = $this->car($this->camry, 2020);
        $other = User::factory()->create(['company_id' => $this->store->id]);
        $other->assignRole('customer');

        $this->actingAs($other, 'sanctum');
        $this->assertSame([], $this->getJson('/api/garage')->json());
        $this->putJson("/api/garage/{$id}", ['nickname' => 'mine now'])->assertNotFound();
        $this->deleteJson("/api/garage/{$id}")->assertNotFound();

        $this->actingAs($this->customer, 'sanctum');
        for ($i = 0; $i < 9; $i++) {
            $this->car($this->corolla, 2015 + $i);
        }
        $this->postJson('/api/garage', ['vehicle_model_id' => $this->camry->id, 'year' => 2021])->assertUnprocessable();       // ten is the limit
    }

    public function test_what_a_product_fits_follows_make_model_and_years(): void
    {
        $fits = app(FitmentService::class);
        $universal = Product::find($this->part('Wiper'));
        $this->assertSame('universal', $fits->check($universal, $this->camry->id, 2020));

        $part = $this->part('Camry bumper');
        $this->actingAs($this->seller, 'sanctum')->putJson("/api/products/{$part}/fitments", ['fitment_required' => true, 'rows' => [
            ['vehicle_model_id' => $this->camry->id, 'year_from' => 2018, 'year_to' => 2022, 'note' => 'Not the hybrid'],
            ['vehicle_make_id' => $this->bmw->id],                          // every BMW, any year
        ]])->assertOk()->assertJsonCount(2, 'fitments');

        $p = Product::find($part);
        $this->assertSame('fits', $fits->check($p, $this->camry->id, 2020));
        $this->assertSame('fits', $fits->check($p, $this->camry->id, 2018));        // the range includes both ends
        $this->assertSame('fits', $fits->check($p, $this->camry->id, 2022));
        $this->assertSame('does_not_fit', $fits->check($p, $this->camry->id, 2023));
        $this->assertSame('does_not_fit', $fits->check($p, $this->camry->id, 2017));
        $this->assertSame('does_not_fit', $fits->check($p, $this->corolla->id, 2020));    // same make, other model
        $this->assertSame('fits', $fits->check($p, $this->x5->id, 2001));                 // a make row covers its models
        $this->assertSame('unknown', $fits->check($p, null, null));
    }

    public function test_fitment_rows_are_checked_and_belong_to_the_products_owner(): void
    {
        $part = $this->part('Camry mirror');

        $this->actingAs($this->seller, 'sanctum');
        $this->putJson("/api/products/{$part}/fitments", ['rows' => [['vehicle_make_id' => $this->bmw->id, 'vehicle_model_id' => $this->camry->id]]])->assertUnprocessable();    // a Camry is not a BMW
        $this->putJson("/api/products/{$part}/fitments", ['rows' => [['vehicle_model_id' => 999999]]])->assertUnprocessable();
        $this->putJson("/api/products/{$part}/fitments", ['rows' => [['year_from' => 2020, 'year_to' => 2018]]])->assertUnprocessable();
        $this->putJson("/api/products/{$part}/fitments", [])->assertUnprocessable();                                                            // the list must be sent, even if empty

        $rival = User::factory()->create(['company_id' => Company::create(['tenant_id' => $this->store->tenant_id, 'name' => 'Rival', 'type' => 'supplier', 'status' => 'active'])->id]);
        $rival->assignRole('seller');
        $this->actingAs($rival, 'sanctum')->putJson("/api/products/{$part}/fitments", ['rows' => []])->assertNotFound();
        $this->actingAs($this->customer, 'sanctum')->putJson("/api/products/{$part}/fitments", ['rows' => []])->assertForbidden();
    }

    public function test_the_storefront_shows_what_fits_a_car_and_can_hide_the_rest(): void
    {
        $camry = $this->part('Camry bumper');
        $this->actingAs($this->seller, 'sanctum')->putJson("/api/products/{$camry}/fitments", ['fitment_required' => true, 'rows' => [['vehicle_model_id' => $this->camry->id]]])->assertOk();
        $bmw = $this->part('BMW grille');
        $this->putJson("/api/products/{$bmw}/fitments", ['fitment_required' => true, 'rows' => [['vehicle_make_id' => $this->bmw->id]]])->assertOk();
        $wiper = $this->part('Wiper');

        $list = fn (array $q) => collect($this->getJson('/api/public/storefronts/main-store/products?'.http_build_query($q))->assertOk()->json('data'))->pluck('fits', 'name')->all();
        $this->assertEquals(['BMW grille' => 'does_not_fit', 'Camry bumper' => 'fits', 'Wiper' => 'universal'], collect($list(['vehicle_model_id' => $this->camry->id, 'year' => 2020]))->only(['Camry bumper', 'BMW grille', 'Wiper'])->sortKeys()->all());

        $names = fn (array $q) => array_keys($list($q));
        $only = $names(['vehicle_model_id' => $this->camry->id, 'year' => 2020, 'compatible' => 1]);
        $this->assertContains('Camry bumper', $only);
        $this->assertContains('Wiper', $only);                           // fits everything
        $this->assertNotContains('BMW grille', $only);
        $corolla = $names(['vehicle_model_id' => $this->corolla->id, 'year' => 2020, 'compatible' => 1]);
        $this->assertNotContains('Camry bumper', $corolla);                // same make, other model
        $this->assertContains('Wiper', $corolla);

        // Without a car named nothing is filtered or judged.
        $plain = $this->getJson('/api/public/storefronts/main-store/products')->json('data.0');
        $this->assertArrayNotHasKey('fits', $plain);

        // The product page lists its rows.
        $page = $this->getJson("/api/public/storefronts/main-store/products/{$camry}")->assertOk()->json('product');
        $this->assertTrue($page['fitment_required']);
        $this->assertSame('Camry', $page['fitments'][0]['model']);
    }

    public function test_a_part_that_must_fit_is_bought_for_a_car_it_fits(): void
    {
        $part = $this->part('Camry bumper');
        $this->actingAs($this->seller, 'sanctum')->putJson("/api/products/{$part}/fitments", ['fitment_required' => true, 'rows' => [['vehicle_model_id' => $this->camry->id, 'year_from' => 2018]]])->assertOk();
        $camry = $this->car($this->camry, 2020);
        $old = $this->car($this->camry, 2010);
        $other = $this->car($this->x5, 2020);

        $this->actingAs($this->customer, 'sanctum');
        $this->postJson('/api/cart/items', ['product_id' => $part])->assertUnprocessable()->assertJsonValidationErrors('vehicle_id');                   // no car named
        $this->postJson('/api/cart/items', ['product_id' => $part, 'vehicle_id' => $other])->assertUnprocessable()->assertJsonValidationErrors('vehicle_id');
        $this->postJson('/api/cart/items', ['product_id' => $part, 'vehicle_id' => $old])->assertUnprocessable();                                      // too old
        $this->postJson('/api/cart/items', ['product_id' => $part, 'vehicle_id' => 999999])->assertUnprocessable();                                    // not in the garage

        $cart = $this->postJson('/api/cart/items', ['product_id' => $part, 'vehicle_id' => $camry])->assertCreated()->json();
        $this->assertSame('fits', $cart['items'][0]['fit']);
        $this->assertSame('Toyota Camry 2020', $cart['items'][0]['vehicle']);

        // The order remembers which car it is for.
        $group = $this->postJson('/api/checkout', ['shipping_address' => ['name' => 'Layla', 'phone' => '0501234567', 'line1' => '1 Road', 'city' => 'Riyadh']])->assertCreated()->json();
        $order = \App\Models\Order::withoutGlobalScopes()->where('group_id', $group['id'])->first();
        $this->assertSame(['vehicle_model', $this->camry->id], [$order->subject_type, $order->subject_id]);

        // Another customer's car cannot be used.
        $stranger = User::factory()->create(['company_id' => $this->store->id]);
        $stranger->assignRole('customer');
        $this->actingAs($stranger, 'sanctum')->postJson('/api/cart/items', ['product_id' => $part, 'vehicle_id' => $camry])->assertUnprocessable();
    }

    public function test_a_fitting_request_can_name_a_car_from_the_garage(): void
    {
        $car = $this->car($this->camry, 2020);
        $body = ['parts' => ['Hood'], 'latitude' => 24.7136, 'longitude' => 46.6753, 'location_consent' => true];

        $this->actingAs($this->customer, 'sanctum');
        $id = $this->postJson('/api/installation-requests', $body + ['vehicle_id' => $car])->assertCreated()->json('id');
        $req = \App\Models\InstallationRequest::find($id);
        $this->assertSame('Toyota Camry 2020', $req->subject);
        $this->assertSame($car, (int) $req->customer_vehicle_id);

        $this->postJson('/api/installation-requests', $body)->assertUnprocessable();                                           // a car or a description
        $this->postJson('/api/installation-requests', $body + ['subject' => 'iPhone 14'])->assertCreated();                      // a description still works
        $this->postJson('/api/installation-requests', $body + ['vehicle_id' => 999999])->assertUnprocessable();
    }
}
