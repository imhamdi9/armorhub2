<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Industry;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\IndustrySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** One ArmorHub market per country, running several industries; opened by the platform owner in any country. */
class CountryMarketTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed([IndustrySeeder::class, RolesAndPermissionsSeeder::class]);
        $u = User::factory()->create();
        $u->assignRole('platform-admin');
        Sanctum::actingAs($u);

        return $u;
    }

    public function test_the_owner_opens_a_market_in_any_country_with_the_industries_they_choose(): void
    {
        $this->admin();
        $industries = Industry::whereIn('key', ['automotive', 'devices'])->pluck('id')->all();

        $countries = $this->getJson('/api/admin/countries')->assertOk()->json();
        $this->assertGreaterThan(200, count($countries));
        $this->assertContains('BR', array_column($countries, 'code'));

        $m = $this->postJson('/api/admin/markets', ['country' => 'br', 'industries' => $industries])->assertCreated()->json();
        $this->assertSame('ArmorHub Brazil', $m['name']);                    // named after the country unless chosen
        $this->assertSame('BR', $m['country']);
        $this->assertSame('BRL', $m['currency']);
        $this->assertSame('armorhub-br', $m['store_slug']);
        $this->assertCount(2, $m['industries']);

        // One market per country.
        $this->postJson('/api/admin/markets', ['country' => 'BR', 'industries' => $industries])->assertStatus(422)->assertJsonValidationErrors('country');
        $this->postJson('/api/admin/markets', ['country' => 'ZZ', 'industries' => $industries])->assertStatus(422);
        $this->postJson('/api/admin/markets', ['country' => 'JP', 'industries' => []])->assertStatus(422);

        // The store is public, in the market's currency, and shows products of every industry the market runs.
        $tenant = Tenant::find($m['id']);
        $this->assertSame($industries, $tenant->industries()->pluck('industries.id')->all());
        foreach ($industries as $i) {
            Product::create(['name' => "Item {$i}", 'slug' => "item-{$i}", 'base_price' => 100, 'industry_id' => $i, 'market' => 'BR', 'category' => 'x', 'is_active' => true]);
        }
        $other = Industry::whereNotIn('id', $industries)->first();
        Product::create(['name' => 'Other industry', 'slug' => 'other', 'base_price' => 5, 'industry_id' => $other->id, 'market' => 'BR', 'category' => 'x', 'is_active' => true]);

        $store = $this->getJson('/api/public/storefronts/armorhub-br')->assertOk()->json();
        $this->assertSame('BRL', $store['market']['currency']);
        $this->assertCount(2, $store['industries']);
        $this->assertNull($store['industry']);                                   // several industries: no single one
        $names = collect($this->getJson('/api/public/storefronts/armorhub-br/products')->json('data'))->pluck('name')->all();
        $this->assertEqualsCanonicalizing(['Item '.$industries[0], 'Item '.$industries[1]], $names);
    }

    public function test_a_market_can_be_renamed_paused_and_given_more_industries(): void
    {
        $this->admin();
        $industries = Industry::pluck('id')->all();
        $m = $this->postJson('/api/admin/markets', ['country' => 'AE', 'name' => 'ArmorHub Emirates', 'industries' => [$industries[0]]])->assertCreated()->json();

        $this->putJson("/api/admin/markets/{$m['id']}", ['industries' => [$industries[0], $industries[1]], 'name' => 'ArmorHub UAE'])->assertOk()->assertJsonCount(2, 'industries')->assertJsonPath('name', 'ArmorHub UAE');
        $this->assertSame('ArmorHub UAE', Company::withoutGlobalScopes()->where('slug', 'armorhub-ae')->value('name'));
        $this->putJson("/api/admin/markets/{$m['id']}", ['industries' => []])->assertStatus(422);
        $this->putJson("/api/admin/markets/{$m['id']}", ['status' => 'paused'])->assertOk()->assertJsonPath('status', 'paused');
        $this->getJson('/api/admin/markets')->assertOk()->assertJsonPath('markets.0.stats.sellers', 0);
    }

    public function test_only_platform_admins_open_markets_and_any_country_gets_a_working_pack(): void
    {
        $this->seed([IndustrySeeder::class, RolesAndPermissionsSeeder::class]);
        $owner = User::factory()->create();
        $owner->assignRole('company-owner');
        Sanctum::actingAs($owner);
        $this->postJson('/api/admin/markets', ['country' => 'BR', 'industries' => [1]])->assertForbidden();

        $m = app(\App\Support\Markets\Markets::class)->get('BR');
        $this->assertSame('BRL', $m->currency());
        $this->assertSame('+5511912345678', $m->normalizePhone('11 91234 5678'));
        $this->assertSame('البرازيل', $m->name('ar'));
        $this->assertNull(app(\App\Support\Markets\Markets::class)->get('XX'));
    }

    public function test_a_seller_appears_only_inside_its_own_market_on_a_page_that_is_not_a_store(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $this->artisan('platform:demo-egypt')->assertSuccessful();
        $khan = Company::withoutGlobalScopes()->where('name', 'Khan El-Khalili Crafts')->firstOrFail();

        $page = $this->getJson("/api/public/storefronts/armorhub-eg/sellers/{$khan->id}")->assertOk()->json();
        $this->assertSame('Khan El-Khalili Crafts', $page['seller']['name']);
        $this->assertGreaterThan(0, $page['products']['total']);
        $this->assertSame([$khan->id], collect($page['products']['data'])->pluck('seller_id')->unique()->values()->all());

        // Another country's market does not list it, a service-centre company is not a seller, and a suspended seller disappears.
        $this->admin();
        $m = $this->postJson('/api/admin/markets', ['country' => 'AE', 'industries' => [Industry::value('id')]])->assertCreated()->json();
        $this->getJson("/api/public/storefronts/{$m['store_slug']}/sellers/{$khan->id}")->assertNotFound();
        $centre = Company::withoutGlobalScopes()->where('name', 'Zamalek Detailing Studio')->firstOrFail();
        $this->getJson("/api/public/storefronts/armorhub-eg/sellers/{$centre->id}")->assertNotFound();
        $khan->update(['status' => 'suspended']);
        $this->getJson("/api/public/storefronts/armorhub-eg/sellers/{$khan->id}")->assertNotFound();
    }

    public function test_older_per_industry_markets_of_a_country_are_merged_from_the_admin_screen(): void
    {
        $this->seed([\Database\Seeders\IndustrySeeder::class, \Database\Seeders\RolesAndPermissionsSeeder::class]);
        [$a, $b] = Industry::orderBy('id')->take(2)->get();
        $mk = function (string $name, Industry $i, string $slug) {
            $t = Tenant::create(['name' => $name, 'industry_id' => $i->id, 'market' => 'EG']);
            $t->industries()->attach($i->id);
            $c = Company::create(['tenant_id' => $t->id, 'name' => $name, 'type' => 'partner', 'status' => 'active', 'slug' => $slug, 'settings' => ['storefront' => ['enabled' => true]]]);

            return [$t, $c];
        };
        [$t1] = $mk('Pharaoh Auto', $a, 'pharaoh-auto');
        [$t2, $c2] = $mk('Beit Masr', $b, 'beit-masr');
        $user = User::factory()->create(['company_id' => $c2->id]);
        $admin = User::factory()->create();
        $admin->assignRole('platform-admin');

        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/markets/consolidate')->assertOk()->assertJson(['folded' => 1]);
        $this->postJson('/api/admin/markets/consolidate')->assertOk()->assertJson(['folded' => 0]);   // safe to run again

        $markets = $this->getJson('/api/admin/markets')->assertOk()->json('markets');
        $this->assertCount(1, $markets);
        $this->assertCount(2, $markets[0]['industries']);
        $this->getJson('/api/public/storefronts/beit-masr')->assertOk()->assertJson(['merged_into' => 'pharaoh-auto']);
        $this->assertSame(Company::withoutGlobalScopes()->where('slug', 'pharaoh-auto')->value('id'), $user->fresh()->company_id);
    }
}
