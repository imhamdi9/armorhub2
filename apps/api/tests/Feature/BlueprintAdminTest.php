<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Product;
use App\Models\Tenant;
use App\Modules\Automotive\Models\VehiclePart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class BlueprintAdminTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
    }

    public function test_only_platform_admins_use_blueprints(): void
    {
        foreach ([$this->customer, $this->manager, $this->tech] as $nobody) {
            $this->actingAs($nobody, 'sanctum')->getJson('/api/admin/platform/blueprints')->assertForbidden();
            $this->postJson('/api/admin/platform/blueprints/armorhub.automotive-egypt/apply', ['params' => ['name' => 'X']])->assertForbidden();
        }
    }

    public function test_the_admin_launches_a_working_egyptian_market_from_a_blueprint(): void
    {
        $list = $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/platform/blueprints')->assertOk()->json('blueprints');
        $this->assertContains('armorhub.automotive-egypt', array_column($list, 'id'));

        $res = $this->postJson('/api/admin/platform/blueprints/armorhub.automotive-egypt/apply', ['params' => ['name' => 'ArmorHub Egypt']])->assertCreated();
        $tenant = Tenant::find($res->json('tenant_id'));
        $this->assertSame('EG', $tenant->market);
        $this->assertSame('automotive', $tenant->industry->key);
        $slug = $res->json('store_slug');
        $this->assertNotNull($slug);

        // The market is live: its store is public, priced in Egyptian pounds, with starter products and a working price list.
        $this->assertContains($slug, array_column($this->getJson('/api/public/storefronts')->json('stores'), 'slug'));
        $products = $this->getJson("/api/public/storefronts/{$slug}/products")->assertOk()->json('data');
        $names = array_column($products, 'name');
        $this->assertContains('Paint Protection Film', $names);   // the market's own starter products (the shared catalog's product shows too)
        $this->assertContains('Ceramic Coating', $names);
        $this->assertSame('EGP', $products[0]['currency']);
        $film = collect($products)->firstWhere('name', 'Paint Protection Film');
        $this->assertEquals(3250, $film['from_price']);                    // the cheapest option: mirrors at 250 x 13
        $detail = $this->getJson("/api/public/storefronts/{$slug}/products/{$film['id']}")->assertOk()->json('product');
        $this->assertEquals(15600, collect($detail['prices'])->firstWhere('target', 'Hood')['price']);

        // The shared catalog it needed was created (existing entries untouched).
        $this->assertSame(1, VehiclePart::where('name', 'Hood')->count());
        $this->assertSame(1, AuditLog::where('action', 'platform.blueprint_applied')->count());
    }

    public function test_applying_twice_changes_nothing_and_bad_answers_are_refused(): void
    {
        $this->actingAs($this->admin, 'sanctum');
        $a = $this->postJson('/api/admin/platform/blueprints/armorhub.automotive-saudi/apply', ['params' => ['name' => 'ArmorHub Saudi']])->assertCreated();
        $b = $this->postJson('/api/admin/platform/blueprints/armorhub.automotive-saudi/apply', ['params' => ['name' => 'ArmorHub Saudi']])->assertOk();
        $this->assertSame($a->json('id'), $b->json('id'));
        $this->assertSame(1, Company::where('name', 'ArmorHub Saudi')->count());
        $this->assertSame(1, Tenant::where('name', 'ArmorHub Saudi')->count());

        $this->postJson('/api/admin/platform/blueprints/armorhub.automotive-saudi/apply', ['params' => []])->assertUnprocessable()->assertJsonValidationErrors('params');
        $this->postJson('/api/admin/platform/blueprints/armorhub.automotive-saudi/apply', ['params' => ['name' => 'X', 'extra' => 'y']])->assertUnprocessable();
        $this->postJson('/api/admin/platform/blueprints/armorhub.home-services/apply', ['params' => ['name' => 'X', 'country' => 'ZZ']])->assertUnprocessable();   // unknown country: nothing half-created
        $this->assertSame(0, Tenant::where('name', 'X')->count());
        $this->postJson('/api/admin/platform/blueprints/nope.nothing/apply', ['params' => []])->assertNotFound();

        $this->getJson('/api/admin/platform/blueprints/applications')->assertOk()->assertJsonCount(1, 'applications');
    }

    public function test_a_blueprint_can_change_a_markets_behaviour_not_just_create_it(): void
    {
        $this->actingAs($this->admin, 'sanctum');
        $res = $this->postJson('/api/admin/platform/blueprints/armorhub.home-services/apply', ['params' => ['name' => 'Cairo Homes', 'country' => 'EG']])->assertCreated();

        $wf = $this->getJson("/api/admin/platform/workflows?tenant_id={$res->json('tenant_id')}")->json('workflows.0');
        $this->assertTrue($wf['customised']);
        $this->assertContains('paid', $wf['active']['transitions']['cancel']['from']);
        $this->assertNotContains('paid', $wf['default']['transitions']['cancel']['from']);   // the product default is untouched
    }
}
