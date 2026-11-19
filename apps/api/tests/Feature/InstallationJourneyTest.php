<?php

namespace Tests\Feature;

use App\Models\CentreWalletEntry;
use App\Models\InstallationRequest;
use App\Models\Partner;
use App\Models\User;
use App\Services\InstallationJobs;
use App\Services\Payment\PaymentEvent;
use App\Services\Payment\PaymentWebhookHandler;
use App\Services\Reputation;
use App\Support\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Accept, pay, hold, prove, release, withdraw, dispute: the whole money path of an installation job. */
class InstallationJourneyTest extends TestCase
{
    use RefreshDatabase;

    private const CUSTOMER = 'nour.fawzy@demo.eg';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->artisan('platform:demo-egypt')->assertSuccessful();
    }

    private function auto(): int
    {
        return (int) \App\Models\Industry::where('key', 'automotive')->value('id');
    }

    private function as(string $email): static
    {
        return $this->actingAs(User::where('email', $email)->firstOrFail(), 'sanctum');
    }

    private function centreEmail(string $name = 'Nasr City Auto Film'): string
    {
        return User::where('partner_id', Partner::withoutGlobalScopes()->where('name', $name)->value('id'))->role('partner-manager')->value('email');
    }

    private function photo(string $name = 'a.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 400, 300);
    }

    /** A request with one offer of $price from Nasr City Auto Film, accepted by the customer. */
    private function accepted(float $price = 9000, array $accept = [], ?\Closure $beforeAccept = null): array
    {
        $this->as(self::CUSTOMER);
        $id = $this->postJson('/api/installation-requests', ['industry_id' => $this->auto(), 'subject' => 'Toyota Camry 2020', 'parts' => ['Hood'], 'latitude' => 30.0626, 'longitude' => 31.2197, 'location_consent' => true])->assertCreated()->json('id');
        $this->as($this->centreEmail())->postJson("/api/partner/installation-requests/{$id}/offer", ['price' => $price, 'eta_days' => 2])->assertCreated();
        $beforeAccept && $beforeAccept();
        $offer = $this->as(self::CUSTOMER)->getJson("/api/installation-requests/{$id}")->json('offers.0.id');
        $this->postJson("/api/installation-requests/{$id}/accept", ['offer_id' => $offer, 'accept_terms' => true] + $accept)->assertOk();

        return [$id, $offer];
    }

    private function paid(float $price = 9000, array $accept = []): int
    {
        [$id] = $this->accepted($price, $accept);
        $this->as(self::CUSTOMER)->postJson("/api/installation-requests/{$id}/pay")->assertOk()->assertJsonPath('status', 'paid');

        return $id;
    }

    private function proven(float $price = 9000): int
    {
        $id = $this->paid($price);
        $this->as($this->centreEmail())->postJson("/api/partner/installation-jobs/{$id}/start")->assertOk();
        $this->postJson("/api/partner/installation-jobs/{$id}/proof", [
            'before' => [$this->photo('b.jpg')], 'after' => [$this->photo('c.jpg')], 'serial' => 'SN-2201', 'latitude' => 30.0627, 'longitude' => 31.2198, 'taken_at' => now()->toIso8601String(),
        ])->assertOk()->assertJsonPath('status', 'proof_submitted');

        return $id;
    }

    private function wallet(string $currency = 'EGP'): array
    {
        return $this->as($this->centreEmail())->getJson('/api/partner/wallet')->assertOk()->json("balances.{$currency}") ?? [];
    }

    public function test_the_terms_are_required_and_the_fee_comes_from_the_market(): void
    {
        $this->as(self::CUSTOMER);
        $id = $this->postJson('/api/installation-requests', ['industry_id' => $this->auto(), 'subject' => 'Camry', 'parts' => ['Hood'], 'latitude' => 30.06, 'longitude' => 31.21, 'location_consent' => true])->json('id');
        $this->as($this->centreEmail())->postJson("/api/partner/installation-requests/{$id}/offer", ['price' => 1000, 'eta_days' => 1])->assertCreated();
        $offer = $this->as(self::CUSTOMER)->getJson("/api/installation-requests/{$id}")->json('offers.0.id');

        $this->postJson("/api/installation-requests/{$id}/accept", ['offer_id' => $offer, 'accept_terms' => false])->assertStatus(422);
        app(Settings::class)->set('installation.fee_percent.EG', 8);
        $this->postJson("/api/installation-requests/{$id}/accept", ['offer_id' => $offer, 'accept_terms' => true])->assertOk()->assertJsonPath('status', 'awaiting_payment');

        $r = InstallationRequest::find($id);
        $this->assertSame('EG', $r->market);
        $this->assertEquals(8.0, (float) $r->fee_percent);   // the Egyptian rate, not the 5% default
        $this->assertEquals(80, (float) $r->fee_amount);
        $this->assertEquals(920, (float) $r->centre_amount);
        $this->assertEquals(1000, (float) $r->pay_amount);
    }

    public function test_details_appear_only_after_payment_and_the_number_is_a_substitute(): void
    {
        [$id] = $this->accepted();
        $before = $this->as(self::CUSTOMER)->getJson("/api/installation-requests/{$id}")->assertOk()->json();
        $this->assertArrayNotHasKey('name', $before['centre']);
        $this->assertArrayNotHasKey('maps_url', $before['centre']);
        $this->as($this->centreEmail())->getJson("/api/partner/installation-jobs/{$id}")->assertOk()->assertJsonMissingPath('customer');

        $this->as(self::CUSTOMER)->postJson("/api/installation-requests/{$id}/pay")->assertOk();
        $customer = $this->getJson("/api/installation-requests/{$id}")->json();
        $this->assertSame('Nasr City Auto Film', $customer['centre']['name']);
        $this->assertStringStartsWith('https://www.google.com/maps/search/?api=1&query=', $customer['centre']['maps_url']);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $customer['centre']['call']['extension']);
        $this->assertStringNotContainsString('+20 10', json_encode($customer));        // the centre's real phone is never shown

        $centre = $this->as($this->centreEmail())->getJson("/api/partner/installation-jobs/{$id}")->json();
        $this->assertSame('Nour El-Din Fawzy', $centre['customer']['name']);
        $this->assertStringContainsString('30.0626,31.2197', $centre['customer']['maps_url']);
        $this->assertEquals(8550, $centre['you_earn']);                                // 9000 less the 5% fee
        $this->assertSame('held', $centre['money']);
        $this->assertEquals(8550, $this->wallet()['held']);
    }

    public function test_proof_starts_the_window_confirming_releases_and_the_customer_earns_points_and_rates(): void
    {
        $id = $this->proven();
        $r = InstallationRequest::find($id);
        $this->assertEqualsWithDelta(48, now()->diffInHours($r->objection_deadline), 1);
        $this->assertEquals(8550, $this->wallet()['held']);

        $this->as(self::CUSTOMER)->postJson("/api/installation-requests/{$id}/confirm")->assertOk()->assertJsonPath('status', 'released');
        $this->assertEquals(8550, $this->wallet()['available']);
        $this->assertSame(900, app(Reputation::class)->points(User::where('email', self::CUSTOMER)->value('id')));   // 1 point per 10 EGP

        $this->as(self::CUSTOMER)->postJson("/api/installation-requests/{$id}/review", ['rating' => 5, 'comment' => 'Perfect work'])->assertOk()->assertJsonPath('review.rating', 5);
        $this->postJson("/api/installation-requests/{$id}/review", ['rating' => 5])->assertStatus(422);                 // once only
        $this->assertSame(905, app(Reputation::class)->points(User::where('email', self::CUSTOMER)->value('id')));

        $p = Partner::withoutGlobalScopes()->where('name', 'Nasr City Auto Film')->first();
        $this->assertSame(1, $p->completed_jobs);
        $this->assertEquals(5.0, (float) $p->rating_avg);
        $this->assertNotNull($p->avg_response_minutes);
    }

    public function test_an_unconfirmed_job_is_released_after_48_hours_unless_disputed(): void
    {
        $quiet = $this->proven();
        $disputed = $this->proven(5000);
        $this->as(self::CUSTOMER)->postJson("/api/installation-requests/{$disputed}/disputes", ['type' => 'defect', 'description' => 'The film has bubbles on the hood'])->assertOk()->assertJsonPath('status', 'disputed');

        $this->travel(47)->hours();
        $this->as(self::CUSTOMER)->getJson("/api/installation-requests/{$quiet}")->assertJsonPath('status', 'proof_submitted');
        $this->travel(2)->hours();
        $this->getJson("/api/installation-requests/{$quiet}")->assertJsonPath('status', 'released');   // released as people look: no scheduler needed
        $this->assertSame('disputed', InstallationRequest::find($disputed)->status);                   // a dispute stops the clock
        $this->assertEquals(8550.0, $this->wallet()['available']);
        $this->assertEquals(4750.0, $this->wallet()['held']);
    }

    public function test_withdrawals_check_the_balance_and_the_admin_records_payment(): void
    {
        $id = $this->proven();
        $this->as(self::CUSTOMER)->postJson("/api/installation-requests/{$id}/confirm")->assertOk();
        $body = ['currency' => 'EGP', 'method' => 'bank', 'account_name' => 'Nasr City Auto Film', 'account_number' => 'EG380019000500000000263180002', 'bank_name' => 'NBE'];

        $this->as($this->centreEmail());
        $this->postJson('/api/partner/wallet/withdrawals', $body + ['amount' => 50])->assertStatus(422)->assertJsonValidationErrors('amount');       // below the minimum
        $this->postJson('/api/partner/wallet/withdrawals', $body + ['amount' => 9999])->assertStatus(422)->assertJsonValidationErrors('amount');     // more than available
        $w1 = $this->postJson('/api/partner/wallet/withdrawals', $body + ['amount' => 5000])->assertCreated()->json('id');
        $this->assertEquals(3550, $this->wallet()['available']);
        $this->assertEquals(5000, $this->wallet()['pending_withdrawal']);
        $this->getJson('/api/partner/wallet')->assertJsonMissingPath('withdrawals.0.account_number');            // only the last digits come back
        $this->postJson('/api/partner/wallet/withdrawals', ['method' => 'wallet', 'account_number' => '01012345678', 'account_name' => 'X', 'amount' => 1000, 'currency' => 'EGP'])->assertCreated();

        $this->as('admin@demo.eg');
        $this->postJson("/api/admin/centre-withdrawals/{$w1}/pay", ['reference' => 'BANK-77'])->assertOk();
        $list = $this->getJson('/api/admin/centre-withdrawals')->json();
        $this->assertSame('paid', collect($list)->firstWhere('id', $w1)['status']);
        $second = collect($list)->firstWhere('status', 'requested')['id'];
        $this->postJson("/api/admin/centre-withdrawals/{$second}/reject", ['note' => 'Wrong number'])->assertOk();

        $this->postJson("/api/admin/centre-withdrawals/{$w1}/pay", ['reference' => 'again'])->assertStatus(422); // not twice
        $this->assertEquals(5000, $this->wallet()['withdrawn']);
        $this->assertEquals(3550, $this->wallet()['available']);                                                  // the rejected one is available again
    }

    public function test_loyalty_points_give_a_discount_that_never_exceeds_the_platform_fee(): void
    {
        $uid = User::where('email', self::CUSTOMER)->value('id');
        app(Reputation::class)->earn($uid, 5000, 'job');           // far more points than the fee is worth

        [$id] = $this->accepted(1000, ['use_points' => 5000]);
        $r = InstallationRequest::find($id);
        $this->assertEquals(50, (float) $r->fee_amount);
        $this->assertEquals(50, (float) $r->discount_amount);       // capped at the 5% fee; 200 points at 0.25 each
        $this->assertSame(200, $r->points_used);
        $this->assertEquals(950, (float) $r->pay_amount);
        $this->assertEquals(950, (float) $r->centre_amount);        // the centre still gets price minus the full fee

        $this->as(self::CUSTOMER)->postJson("/api/installation-requests/{$id}/pay")->assertOk();
        $this->assertSame(4800, app(Reputation::class)->points($uid)); // 5000 less the 200 spent; points for this job come when it is released
    }

    public function test_a_partial_refund_recomputes_the_centre_share_and_a_full_refund_voids_it(): void
    {
        $partial = $this->proven();
        $this->as(self::CUSTOMER)->postJson("/api/installation-requests/{$partial}/disputes", ['type' => 'defect', 'description' => 'Edges are lifting on the bumper'])->assertOk();
        $this->postJson("/api/installation-requests/{$partial}/evidence", ['note' => 'Photo of the lifting edge', 'photos' => [$this->photo('e.jpg')]])->assertOk();
        $this->as($this->centreEmail())->postJson("/api/partner/installation-jobs/{$partial}/evidence", ['note' => 'Customer washed it with a pressure washer too early'])->assertOk();

        $this->as('admin@demo.eg');
        $dispute = collect($this->getJson('/api/admin/installation-disputes')->json())->firstWhere('job.id', $partial);
        $this->assertCount(5, array_filter($dispute['evidence'], fn ($e) => in_array($e['kind'], ['before', 'after', 'serial', 'evidence'], true)));  // both sides' evidence together
        $this->postJson("/api/admin/installation-disputes/{$dispute['id']}/decide", ['decision' => 'refund_partial', 'refund_amount' => 9000, 'note' => 'x'])->assertStatus(422);   // not the whole price
        $this->postJson("/api/admin/installation-disputes/{$dispute['id']}/decide", ['decision' => 'refund_partial', 'refund_amount' => 2000, 'note' => 'Part of the film must be redone'])->assertOk();

        $r = InstallationRequest::find($partial);
        $this->assertSame('released', $r->status);
        $this->assertEquals(7000 * 0.95, (float) $r->centre_amount);                                // 7,000 left, 5% fee on that
        $this->assertSame('pending', $r->refund_status);
        $this->postJson("/api/admin/installation-requests/{$partial}/refund-paid", ['reference' => 'RF-1'])->assertOk();
        $this->assertSame('refunded', $r->fresh()->refund_status);
        $this->assertEquals(6650, $this->wallet()['available']);

        $full = $this->proven(4000);
        $this->as(self::CUSTOMER)->postJson("/api/installation-requests/{$full}/disputes", ['type' => 'not_done', 'description' => 'Nobody came to finish it'])->assertOk();
        $d = collect($this->as('admin@demo.eg')->getJson('/api/admin/installation-disputes')->json())->firstWhere('job.id', $full);
        $this->postJson("/api/admin/installation-disputes/{$d['id']}/decide", ['decision' => 'refund_full', 'note' => 'Work was not completed'])->assertOk();
        $this->assertSame('refunded', InstallationRequest::find($full)->status);
        $this->assertEquals(0, CentreWalletEntry::where('request_id', $full)->where('status', '!=', 'void')->count());
        $this->assertSame(2, Partner::withoutGlobalScopes()->where('name', 'Nasr City Auto Film')->value('disputes_lost'));
    }

    public function test_a_dispute_can_end_with_the_money_released_to_the_centre(): void
    {
        $id = $this->proven();
        $this->as($this->centreEmail())->postJson("/api/partner/installation-jobs/{$id}/disputes", ['type' => 'other', 'description' => 'The customer is not answering to confirm'])->assertOk();
        $d = collect($this->as('admin@demo.eg')->getJson('/api/admin/installation-disputes')->json())->firstWhere('job.id', $id);
        $this->postJson("/api/admin/installation-disputes/{$d['id']}/decide", ['decision' => 'release', 'note' => 'Photos show completed work'])->assertOk();
        $this->assertSame('released', InstallationRequest::find($id)->status);
        $this->postJson("/api/admin/installation-disputes/{$d['id']}/decide", ['decision' => 'release', 'note' => 'again'])->assertStatus(422);
        $this->assertEquals(8550, $this->wallet()['available']);
    }

    public function test_proof_photos_are_private_and_far_ones_are_flagged(): void
    {
        $id = $this->paid();
        $this->as($this->centreEmail())->postJson("/api/partner/installation-jobs/{$id}/proof", [
            'before' => [$this->photo()], 'after' => [$this->photo('z.jpg')], 'latitude' => 31.2, 'longitude' => 29.9, 'serial' => 'S1',    // Alexandria, not Cairo
        ])->assertOk();
        $proofs = $this->as(self::CUSTOMER)->getJson("/api/installation-requests/{$id}")->json('proofs');
        $photo = collect($proofs)->firstWhere('kind', 'before');
        $this->assertTrue($photo['flagged']);
        $this->assertGreaterThan(100, $photo['distance_km']);
        $this->getJson($photo['url'])->assertOk();                                                          // the customer may open it
        $this->as($this->centreEmail())->getJson($photo['url'])->assertOk();                                // the centre too
        $this->as('mariam.ashraf@demo.eg')->getJson($photo['url'])->assertNotFound();                      // nobody else
        $this->as($this->centreEmail('Zamalek Detailing Studio'))->getJson($photo['url'])->assertNotFound();
        $this->as('admin@demo.eg')->getJson($photo['url'])->assertOk();
    }

    public function test_incomplete_proof_does_not_start_the_window(): void
    {
        $id = $this->paid();
        $this->as($this->centreEmail())->postJson("/api/partner/installation-jobs/{$id}/proof", ['before' => [$this->photo()]])->assertOk()->assertJsonPath('status', 'paid');
        $this->postJson("/api/partner/installation-jobs/{$id}/proof", ['after' => [$this->photo()]])->assertOk()->assertJsonPath('status', 'paid');   // still no serial
        $this->postJson("/api/partner/installation-jobs/{$id}/proof", ['serial' => 'SN-1'])->assertOk()->assertJsonPath('status', 'proof_submitted');
        $this->assertNotNull(InstallationRequest::find($id)->objection_deadline);
    }

    public function test_only_the_right_people_can_act_on_a_job(): void
    {
        [$id] = $this->accepted();
        $this->as('mariam.ashraf@demo.eg')->postJson("/api/installation-requests/{$id}/pay")->assertNotFound();
        $this->as($this->centreEmail('Zamalek Detailing Studio'))->getJson("/api/partner/installation-jobs/{$id}")->assertNotFound();
        $this->as($this->centreEmail())->postJson("/api/partner/installation-jobs/{$id}/start")->assertStatus(422);    // not paid yet
        $this->as(self::CUSTOMER)->postJson("/api/installation-requests/{$id}/confirm")->assertStatus(422);
        $this->as(self::CUSTOMER)->getJson('/api/admin/installation-disputes')->assertForbidden();
        $this->as($this->centreEmail())->getJson('/api/admin/centre-withdrawals')->assertForbidden();
    }

    public function test_a_provider_webhook_pays_a_job_only_for_the_exact_amount(): void
    {
        [$id] = $this->accepted(2000);
        $handler = app(PaymentWebhookHandler::class);

        $handler->handle('stripe', new PaymentEvent('paid', 'cs_1', -$id, 100, 'egp'));            // far too little
        $this->assertSame('awaiting_payment', InstallationRequest::find($id)->status);
        $handler->handle('stripe', new PaymentEvent('paid', 'cs_1', -$id, 200000, 'egp'));         // 2,000.00 EGP in piastres
        $this->assertSame('paid', InstallationRequest::find($id)->status);
        $handler->handle('stripe', new PaymentEvent('paid', 'cs_1', -$id, 200000, 'egp'));         // delivered twice: nothing changes
        $this->assertSame(1, CentreWalletEntry::where('request_id', $id)->count());
    }

    public function test_off_platform_dealing_earns_strikes_and_three_suspend_the_centre(): void
    {
        $partner = Partner::withoutGlobalScopes()->where('name', 'Nasr City Auto Film')->first();
        foreach (range(1, 3) as $i) {
            [$id] = $this->accepted();
            $this->as(self::CUSTOMER)->postJson("/api/installation-requests/{$id}/report-contact", ['note' => 'Asked me to pay cash'])->assertOk();
            $v = collect($this->as('admin@demo.eg')->getJson('/api/admin/installation-violations')->json())->firstWhere('request_id', $id);
            $this->postJson("/api/admin/installation-violations/{$v['id']}/decide", ['decision' => 'confirmed'])->assertOk();
            $this->postJson("/api/admin/installation-violations/{$v['id']}/decide", ['decision' => 'dismissed'])->assertStatus(422);
        }
        $partner->refresh();
        $this->assertSame(3, $partner->strikes);
        $this->assertFalse((bool) $partner->is_active);
        $this->assertSame('new', $partner->tier);
    }

    public function test_tiers_follow_the_record_and_good_centres_pay_a_smaller_fee(): void
    {
        $p = Partner::withoutGlobalScopes()->where('name', 'Nasr City Auto Film')->first();
        $rep = app(Reputation::class);
        $this->assertSame('new', $rep->tier($p, 0, null, 0));
        $this->assertSame('bronze', $rep->tier($p, 3, 4.0, 3));
        $this->assertSame('silver', $rep->tier($p, 15, 4.4, 8));
        $this->assertSame('gold', $rep->tier($p, 50, 4.8, 20));
        $this->assertSame('silver', $rep->tier($p, 50, 4.5, 20));       // not good enough for gold
        [$id] = $this->accepted(1000, [], function () use ($p) {
            $p->forceFill(['tier' => 'gold'])->save();      // earned after the offer was made
            $this->assertEquals(1.0, app(Reputation::class)->feeDiscount($p));
        });
        $this->assertEquals(4.0, (float) InstallationRequest::find($id)->fee_percent);          // 5% less the gold discount
    }

    public function test_a_product_sold_only_with_an_installation_offer_cannot_be_bought_alone(): void
    {
        $lock = \App\Models\Product::where('slug', 'eg-doorlock')->firstOrFail();
        $this->assertSame('required', $lock->installation);
        $rule = $lock->priceRules()->first();
        $order = ['product_id' => $lock->id, 'target_type' => $rule->target_type, 'targets' => [$rule->target_id],
            'shipping_address' => ['name' => 'Nour', 'phone' => '01012345678', 'line1' => '1 Street', 'city' => 'Giza', 'country' => 'EG']];

        // Nobody can buy it directly: not a signed-in customer, not a guest.
        $this->as('khaled.abdelrahman@demo.eg')->postJson('/api/orders', $order)->assertStatus(422)->assertJsonValidationErrors('installation');
        $challenge = $this->getJson('/api/signup-challenge')->json();
        $this->travel(5)->seconds();
        $this->postJson('/api/guest-orders', ['store' => 'armorhub-eg', 'product_id' => $lock->id, 'name' => 'G', 'email' => 'g@example.org', 'phone' => '01012345678',
            'address' => ['line1' => '1 St', 'city' => 'Giza'], 'signup_token' => $challenge['token']])->assertStatus(422)->assertJsonValidationErrors('product_id');

        // A request for this product whose offer is only on the table (not accepted yet) is not enough either.
        $req = $this->as('khaled.abdelrahman@demo.eg')->postJson('/api/installation-requests', ['subject' => 'Front door', 'parts' => ['Smart lock'], 'product_id' => $lock->id,
            'latitude' => 30.0131, 'longitude' => 31.2089, 'location_consent' => true])->assertCreated()->json();
        $this->postJson('/api/orders', $order + ['installation_request_id' => $req['id']])->assertStatus(422);

        // With an accepted installation offer the product can be bought, once, and the two are linked.
        $this->as($this->centreEmail('Giza CleanPro'))->postJson("/api/partner/installation-requests/{$req['id']}/offer", ['price' => 800, 'eta_days' => 2])->assertCreated();
        $offer = $this->as('khaled.abdelrahman@demo.eg')->getJson("/api/installation-requests/{$req['id']}")->json('offers.0.id');
        $this->postJson("/api/installation-requests/{$req['id']}/accept", ['offer_id' => $offer, 'accept_terms' => true])->assertOk();
        $this->postJson('/api/orders', $order + ['installation_request_id' => $req['id']])->assertCreated();
        $this->assertNotNull(InstallationRequest::find($req['id'])->order_id);
        $this->postJson('/api/orders', $order + ['installation_request_id' => $req['id']])->assertStatus(422);        // one installation job, one order
    }
}
