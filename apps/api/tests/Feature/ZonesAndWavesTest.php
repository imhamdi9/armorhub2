<?php

namespace Tests\Feature;

use App\Models\InstallationInvite;
use App\Models\InstallationRequest;
use App\Models\Partner;
use App\Models\ServiceZone;
use App\Models\User;
use App\Services\InstallationMatching;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Opening a market zone by zone, and asking the nearest few centres first. */
class ZonesAndWavesTest extends TestCase
{
    use RefreshDatabase;

    private const CUSTOMER = 'nour.fawzy@demo.eg';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->artisan('platform:demo-egypt')->assertSuccessful();
    }

    private function as(string $email): static
    {
        return $this->actingAs(User::where('email', $email)->firstOrFail(), 'sanctum');
    }

    private function admin(): static
    {
        return $this->actingAs(User::role('platform-admin')->firstOrFail(), 'sanctum');
    }

    private function body(array $extra = []): array
    {
        return $extra + ['industry_id' => (int) \App\Models\Industry::where('key', 'automotive')->value('id'), 'subject' => 'Toyota Camry 2020', 'parts' => ['Hood'], 'latitude' => 30.0626, 'longitude' => 31.2197, 'location_consent' => true];
    }

    public function test_a_market_without_zones_is_open_everywhere_as_before(): void
    {
        $this->as(self::CUSTOMER)->postJson('/api/installation-requests', $this->body(['latitude' => 31.2, 'longitude' => 29.9]))->assertCreated();    // Alexandria
    }

    public function test_the_administrator_manages_zones_and_nobody_else_can(): void
    {
        $this->admin();
        $zone = $this->postJson('/api/admin/zones', ['market' => 'eg', 'key' => 'nasr-city', 'name_en' => 'Nasr City', 'name_ar' => 'مدينة نصر', 'city' => 'Cairo', 'latitude' => 30.056, 'longitude' => 31.33, 'radius_km' => 8, 'is_active' => true])
            ->assertCreated()->json();
        $this->assertSame('EG', $zone['market']);
        $this->assertGreaterThanOrEqual(1, $zone['centres']);                              // the demo centre in Nasr City is inside

        $this->postJson('/api/admin/zones', ['market' => 'EG', 'key' => 'nasr-city', 'name_en' => 'Again', 'latitude' => 1, 'longitude' => 1])->assertUnprocessable();    // the key is unique in a market
        $this->postJson('/api/admin/zones', ['market' => 'EG', 'key' => 'Bad Key!', 'name_en' => 'x', 'latitude' => 1, 'longitude' => 1])->assertUnprocessable();
        $this->postJson('/api/admin/zones', ['market' => 'EG', 'key' => 'far', 'name_en' => 'x', 'latitude' => 99, 'longitude' => 1])->assertUnprocessable();
        $this->postJson('/api/admin/zones', ['market' => 'EG', 'key' => 'wide', 'name_en' => 'x', 'latitude' => 1, 'longitude' => 1, 'radius_km' => 5000])->assertUnprocessable();

        $this->putJson("/api/admin/zones/{$zone['id']}", ['radius_km' => 12, 'is_active' => false])->assertOk()->assertJsonPath('radius_km', 12)->assertJsonPath('is_active', false);

        $this->as(self::CUSTOMER)->getJson('/api/admin/zones')->assertForbidden();
        $this->postJson('/api/admin/zones', [])->assertForbidden();
        $this->admin()->deleteJson("/api/admin/zones/{$zone['id']}")->assertOk();
    }

    public function test_the_public_sees_only_the_open_zones(): void
    {
        ServiceZone::create(['market' => 'EG', 'key' => 'open', 'name_en' => 'Open one', 'latitude' => 30.05, 'longitude' => 31.33, 'is_active' => true]);
        ServiceZone::create(['market' => 'EG', 'key' => 'closed', 'name_en' => 'Closed one', 'latitude' => 30.0, 'longitude' => 31.5, 'is_active' => false]);

        $zones = $this->getJson('/api/public/zones?market=EG')->assertOk()->json('zones');
        $this->assertSame(['open'], array_column($zones, 'key'));
        $this->assertArrayNotHasKey('latitude', $zones[0]);
    }

    public function test_a_request_inside_an_open_zone_is_served_and_outside_it_is_noted_as_demand(): void
    {
        $zone = ServiceZone::create(['market' => 'EG', 'key' => 'zamalek', 'name_en' => 'Zamalek', 'city' => 'Cairo', 'latitude' => 30.0626, 'longitude' => 31.2197, 'radius_km' => 5, 'is_active' => true]);

        $id = $this->as(self::CUSTOMER)->postJson('/api/installation-requests', $this->body())->assertCreated()->json('id');
        $this->assertSame($zone->id, (int) InstallationRequest::find($id)->zone_id);

        // Alexandria is outside every open zone: refused with what is open, and the attempt is kept (rounded, with the customer's id).
        $this->postJson('/api/installation-requests', $this->body(['latitude' => 31.2001, 'longitude' => 29.9187]))->assertUnprocessable()->assertJsonValidationErrors('location');
        $row = DB::table('zone_waitlist')->first();
        $this->assertSame([31.2, 29.92], [(float) $row->latitude, (float) $row->longitude]);       // about a kilometre, not a doorstep
        $this->assertSame(1, DB::table('zone_waitlist')->count());

        $this->postJson('/api/installation-requests', $this->body(['latitude' => 31.2001, 'longitude' => 29.9187]))->assertUnprocessable();
        $this->assertSame(1, DB::table('zone_waitlist')->count());                                   // asking again is not counted again

        // The administrator sees where the demand is.
        $demand = $this->admin()->getJson('/api/admin/zones/demand')->assertOk()->json();
        $this->assertSame(1, (int) $demand[0]['customers']);
    }

    public function test_the_overlap_goes_to_the_nearest_zone_centre(): void
    {
        ServiceZone::create(['market' => 'EG', 'key' => 'wide', 'name_en' => 'Wide', 'latitude' => 30.0, 'longitude' => 31.2, 'radius_km' => 30, 'is_active' => true]);
        $near = ServiceZone::create(['market' => 'EG', 'key' => 'near', 'name_en' => 'Near', 'latitude' => 30.0626, 'longitude' => 31.2197, 'radius_km' => 3, 'is_active' => true]);

        $this->assertSame($near->id, app(\App\Services\ServiceZones::class)->zoneFor('EG', 30.0627, 31.2198)->id);
        $this->assertNull(app(\App\Services\ServiceZones::class)->zoneFor('EG', 31.2, 29.9));
        $this->assertNull(app(\App\Services\ServiceZones::class)->zoneFor(null, 30.0, 31.2));
    }

    public function test_a_request_goes_first_to_the_best_five_and_a_slow_one_is_widened(): void
    {
        // Eight qualifying centres around the customer.
        $base = Partner::withoutGlobalScopes()->where('name', 'Zamalek Detailing Studio')->first();
        foreach (range(1, 8) as $i) {
            $p = $base->replicate();
            $p->name = "Extra Centre {$i}";
            $p->latitude = 30.0626 + $i * 0.004;
            $p->longitude = 31.2197;
            $p->save();
        }

        $id = $this->as(self::CUSTOMER)->postJson('/api/installation-requests', $this->body())->assertCreated()->json('id');
        $req = InstallationRequest::find($id);
        $this->assertSame(5, InstallationInvite::where('request_id', $id)->count());                        // the best five, not everyone
        $this->assertSame(1, InstallationInvite::where('request_id', $id)->max('wave'));

        // A centre that was not asked does not even see the request, and cannot answer it.
        $asked = InstallationInvite::where('request_id', $id)->pluck('partner_id')->all();
        $left = Partner::withoutGlobalScopes()->whereNotIn('id', $asked)->where('name', 'like', 'Extra Centre%')->first();
        $leftEmail = User::factory()->create(['partner_id' => $left->id, 'company_id' => $left->company_id, 'email' => 'left@demo.eg']);
        $leftEmail->assignRole('partner-manager');
        $this->actingAs($leftEmail, 'sanctum');
        $this->assertNotContains($id, array_column($this->getJson('/api/partner/installation-requests')->json(), 'id'));
        $this->postJson("/api/partner/installation-requests/{$id}/offer", ['price' => 1000, 'eta_days' => 1])->assertNotFound();

        $matching = app(InstallationMatching::class);
        // Too soon to widen; and with two offers in there is no need to.
        $this->assertSame(0, $matching->widenDue());

        InstallationInvite::where('request_id', $id)->update(['created_at' => now()->subHours(3)]);
        $this->assertSame(1, $matching->widenDue());                                                        // few answers after 2 hours: the next centres are asked
        $this->assertSame(10, InstallationInvite::where('request_id', $id)->count() + 0 === 10 ? 10 : 0);
        $this->assertSame(2, (int) $req->fresh()->invite_wave);
        $this->assertGreaterThan(5, InstallationInvite::where('request_id', $id)->count());
        $this->assertSame(2, InstallationInvite::where('request_id', $id)->max('wave'));

        // The centre that was left out can now see and answer it.
        $this->actingAs($leftEmail, 'sanctum');
        $this->assertContains($id, array_column($this->getJson('/api/partner/installation-requests')->json(), 'id'));

        // Enough offers: no further widening.
        foreach (InstallationInvite::where('request_id', $id)->take(2)->pluck('partner_id') as $partnerId) {
            \App\Models\InstallationOffer::create(['request_id' => $id, 'partner_id' => $partnerId, 'price' => 1000, 'eta_days' => 1, 'status' => 'submitted']);
        }
        InstallationInvite::where('request_id', $id)->update(['created_at' => now()->subHours(5)]);
        $this->assertSame(0, $matching->widenDue());
    }

    public function test_the_better_centre_is_asked_before_a_nearer_but_worse_one_within_the_same_distance_band(): void
    {
        $zam = Partner::withoutGlobalScopes()->where('name', 'Zamalek Detailing Studio')->first();
        // None of them in the customer's own city, so only distance bands count.
        $zam->update(['rating_avg' => 2.4, 'rating_count' => 4, 'city' => 'Giza']);   // right there, poorly rated (not poor enough to be hidden: that needs 5 ratings)
        $better = $zam->replicate();
        $better->name = 'Better Centre';
        $better->latitude = 30.0626 + 0.03;                                     // about 3 km away: the same distance band
        $better->rating_avg = 4.9;
        $better->rating_count = 30;
        $better->save();
        $far = $zam->replicate();
        $far->name = 'Far Centre';
        $far->latitude = 30.0626 + 0.18;                                        // about 20 km: a further band, however good
        $far->rating_avg = 5.0;
        $far->rating_count = 90;
        $far->save();

        $id = $this->as(self::CUSTOMER)->postJson('/api/installation-requests', $this->body())->assertCreated()->json('id');
        $ranked = app(InstallationMatching::class)->ranked(InstallationRequest::find($id))->pluck('name')->all();

        $this->assertSame('Better Centre', $ranked[0]);                          // quality decides inside a band
        $this->assertSame('Zamalek Detailing Studio', $ranked[1]);
        $this->assertGreaterThan(array_search('Nasr City Auto Film', $ranked), array_search('Far Centre', $ranked));   // a nearer band always comes first, however good the far one
    }

    public function test_the_cairo_zones_are_created_closed_and_opened_on_purpose(): void
    {
        $this->artisan('zones:seed-cairo')->assertSuccessful();
        $this->assertSame(5, ServiceZone::where('market', 'EG')->count());
        $this->assertSame(0, ServiceZone::where('is_active', true)->count());

        $this->artisan('zones:seed-cairo', ['--activate' => true])->assertSuccessful();
        $this->assertSame(5, ServiceZone::where('is_active', true)->count());
        $this->assertSame(5, ServiceZone::count());                                          // running it again adds nothing

        // With the zones open Alexandria is no longer served, and Zamalek (Mohandessin, Dokki and Zamalek) is.
        $this->as(self::CUSTOMER)->postJson('/api/installation-requests', $this->body())->assertCreated();
        $this->postJson('/api/installation-requests', $this->body(['latitude' => 31.2, 'longitude' => 29.9]))->assertUnprocessable();
    }
}
