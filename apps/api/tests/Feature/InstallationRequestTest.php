<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\Product;
use App\Models\User;
use App\Support\ContactGuard;
use App\Support\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Installation help: anonymous requests, ranked offers, no contact details before payment. */
class InstallationRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->artisan('platform:demo-egypt')->assertSuccessful();
    }

    /** The market (ArmorHub Egypt) runs several industries, so a request says which one it is for. */
    private function auto(): int
    {
        return (int) \App\Models\Industry::where('key', 'automotive')->value('id');
    }

    private function as(string $email): static
    {
        return $this->actingAs(User::where('email', $email)->firstOrFail(), 'sanctum');
    }

    private function managerOf(string $partnerName): string
    {
        $partner = Partner::withoutGlobalScopes()->where('name', $partnerName)->firstOrFail();

        return User::where('partner_id', $partner->id)->role('partner-manager')->value('email');
    }

    /** Fake history: reviews are the only way a rating exists, so the engine rebuilds it from them. */
    private function reviews(Partner $p, int $rating, int $n): void
    {
        for ($i = 0; $i < $n; $i++) {
            \App\Models\InstallationReview::create(['request_id' => 9000 + $p->id * 100 + $i, 'partner_id' => $p->id, 'customer_id' => 1, 'rating' => $rating]);
        }
        app(\App\Services\Reputation::class)->refresh($p);
    }

    private function request(array $extra = []): array
    {
        $this->as('nour.fawzy@demo.eg');

        return $this->postJson('/api/installation-requests', $extra + ['industry_id' => $this->auto(),
            'subject' => 'Toyota Camry 2020', 'parts' => ['Hood', 'Bumper'], 'work_type' => 'install', 'notes' => 'Self-healing film please',
            'latitude' => 30.0626, 'longitude' => 31.2197, 'location_consent' => true,
        ])->assertCreated()->json();
    }

    public function test_a_product_says_whether_it_needs_installation(): void
    {
        $this->as('owner.auto@demo.eg');
        $id = Product::where('slug', 'eg-ppf')->value('id');
        $this->putJson("/api/products/{$id}", ['installation' => 'required'])->assertOk();
        $this->putJson("/api/products/{$id}", ['installation' => 'sometimes'])->assertStatus(422);
        $this->assertSame('required', Product::find($id)->installation);
    }

    public function test_a_request_needs_the_customers_allowed_location_and_no_contact_details(): void
    {
        $this->as('nour.fawzy@demo.eg');
        $base = ['subject' => 'Camry', 'parts' => ['Hood'], 'latitude' => 30.06, 'longitude' => 31.21, 'industry_id' => $this->auto()];

        $this->postJson('/api/installation-requests', $base)->assertStatus(422)->assertJsonValidationErrors('location_consent');
        $this->postJson('/api/installation-requests', $base + ['location_consent' => false])->assertStatus(422);
        $this->postJson('/api/installation-requests', ['location_consent' => true] + array_diff_key($base, ['latitude' => 1]))->assertStatus(422)->assertJsonValidationErrors('latitude');
        $this->postJson('/api/installation-requests', $base + ['location_consent' => true, 'notes' => 'call me on 01012345678'])->assertStatus(422);
        $this->postJson('/api/installation-requests', $base + ['location_consent' => true, 'notes' => 'whatsapp me'])->assertStatus(422);
        $this->postJson('/api/installation-requests', $base + ['location_consent' => true])->assertCreated();
    }

    public function test_contact_guard_catches_the_usual_tricks(): void
    {
        foreach (['0101 234 5678', '٠١٠١٢٣٤٥٦٧٨', 'me@example.com', 'https://wa.me/20101', 'my site shop.com', 'واتساب', 'Telegram'] as $bad) {
            $this->assertTrue(ContactGuard::contains($bad), $bad);
        }
        foreach (['Hood and bumper', 'Toyota Camry 2020', 'Fit in 2 days', ''] as $ok) {
            $this->assertFalse(ContactGuard::contains($ok), $ok);
        }
    }

    public function test_centres_see_the_job_but_never_the_customer(): void
    {
        $req = $this->request();
        $this->as($this->managerOf('Zamalek Detailing Studio'));

        $list = $this->getJson('/api/partner/installation-requests')->assertOk()->json();
        $this->assertCount(1, $list);
        $this->assertSame('Toyota Camry 2020', $list[0]['subject']);
        $this->assertSame(['Hood', 'Bumper'], $list[0]['parts']);
        $raw = json_encode($list);
        foreach (['Nour', 'nour.fawzy', 'latitude', 'longitude', '"customer"', '"phone"', '30.0626', 'maps_url'] as $leak) {
            $this->assertStringNotContainsString($leak, $raw, "leaked {$leak}");
        }

        // A centre in another country of the platform (a different market) does not see it: Alexandria is 180 km away.
        $this->as($this->managerOf('Corniche Car Care'));
        $this->assertCount(0, $this->getJson('/api/partner/installation-requests')->json());

        $this->assertSame('open', $req['status']);
    }

    public function test_offers_are_ranked_by_distance_band_then_quality_and_hide_the_centre(): void
    {
        $req = $this->request();
        $zamalek = Partner::withoutGlobalScopes()->where('name', 'Zamalek Detailing Studio')->first();
        $nasr = Partner::withoutGlobalScopes()->where('name', 'Nasr City Auto Film')->first();
        // Both are in Cairo (same city, band 0): the better record comes first even though it is dearer.
        $this->reviews($zamalek, 3, 6);
        $this->reviews($nasr, 5, 6);

        $this->as($this->managerOf('Zamalek Detailing Studio'))->postJson("/api/partner/installation-requests/{$req['id']}/offer", ['price' => 7000, 'eta_days' => 3, 'note' => 'We can do it'])->assertCreated();
        $this->as($this->managerOf('Nasr City Auto Film'))->postJson("/api/partner/installation-requests/{$req['id']}/offer", ['price' => 9000, 'eta_days' => 2])->assertCreated();
        $this->postJson("/api/partner/installation-requests/{$req['id']}/offer", ['price' => 9000, 'eta_days' => 2, 'note' => 'call 01099999999'])->assertStatus(422);

        $this->as('nour.fawzy@demo.eg');
        $view = $this->getJson("/api/installation-requests/{$req['id']}")->assertOk()->json();
        $this->assertEquals([9000, 7000], array_column($view['offers'], 'price'));
        $this->assertSame([1, 2], array_column($view['offers'], 'rank'));
        $this->assertContains('best_rated', $view['offers'][0]['badges']);
        $this->assertContains('fastest', $view['offers'][0]['badges']);

        $raw = json_encode($view);
        foreach (['Zamalek', 'Nasr', 'Studio', 'partner_id', 'latitude', 'longitude', '"phone"', '"address"', 'maps_url', '"call"'] as $leak) {
            $this->assertStringNotContainsString($leak, $raw, "leaked {$leak}");
        }
        $this->assertMatchesRegularExpression('/^C-[0-9A-Z]{4}$/', $view['offers'][0]['centre']);
        $this->assertSame(fmod($view['offers'][0]['distance_km'] * 2, 1.0), 0.0, 'distance is rounded');
    }

    public function test_a_centre_with_a_clearly_poor_record_is_not_offered_the_job(): void
    {
        $req = $this->request();
        $this->reviews(Partner::withoutGlobalScopes()->where('name', 'Zamalek Detailing Studio')->first(), 2, 12);

        $this->as($this->managerOf('Zamalek Detailing Studio'));
        $this->assertCount(0, $this->getJson('/api/partner/installation-requests')->json());
        $this->postJson("/api/partner/installation-requests/{$req['id']}/offer", ['price' => 100, 'eta_days' => 1])->assertNotFound();
    }

    public function test_only_the_owner_sees_a_request_and_the_fee_is_set_per_market(): void
    {
        $req = $this->request();
        $this->as('mariam.ashraf@demo.eg')->getJson("/api/installation-requests/{$req['id']}")->assertNotFound();
        $this->as('nour.fawzy@demo.eg')->postJson("/api/installation-requests/{$req['id']}/cancel")->assertOk()->assertJsonPath('status', 'cancelled');

        $settings = app(Settings::class);
        $this->assertSame(5.0, (float) $settings->get('installation.fee_percent'));
        $this->assertNull($settings->get('installation.fee_percent.EG'));
        $this->assertSame(48, (int) $settings->get('installation.objection_hours'));
    }

    public function test_an_empty_market_fee_means_use_the_default_not_zero(): void
    {
        $settings = app(Settings::class);
        $settings->set('installation.fee_percent.EG', 8);
        $this->assertEquals(8.0, (float) $settings->get('installation.fee_percent.EG'));
        $settings->set('installation.fee_percent.EG', '');
        $this->assertNull($settings->get('installation.fee_percent.EG'));
    }
}
