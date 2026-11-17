<?php

namespace Tests\Feature;

use App\Models\InstallationBooking;
use App\Models\Partner;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** A centre's own rules, its hours and calendar, and the customer's booking of a paid job. */
class CentreBookingTest extends TestCase
{
    use RefreshDatabase;

    private const CUSTOMER = 'nour.fawzy@demo.eg';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->artisan('platform:demo-egypt')->assertSuccessful();
        // A Sunday morning in Cairo: the demo market's week runs Sunday to Thursday.
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-12-06 06:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function as(string $email): static
    {
        return $this->actingAs(User::where('email', $email)->firstOrFail(), 'sanctum');
    }

    private function centre(string $name = 'Nasr City Auto Film'): Partner
    {
        return Partner::withoutGlobalScopes()->where('name', $name)->firstOrFail();
    }

    private function centreEmail(string $name = 'Nasr City Auto Film'): string
    {
        return User::where('partner_id', $this->centre($name)->id)->role('partner-manager')->value('email');
    }

    private function auto(): int
    {
        return (int) \App\Models\Industry::where('key', 'automotive')->value('id');
    }

    private function request(array $extra = []): int
    {
        $this->as(self::CUSTOMER);

        return $this->postJson('/api/installation-requests', $extra + ['industry_id' => $this->auto(), 'subject' => 'Toyota Camry 2020', 'parts' => ['Hood'], 'latitude' => 30.0626, 'longitude' => 31.2197, 'location_consent' => true])->assertCreated()->json('id');
    }

    private function paidJob(): int
    {
        $id = $this->request();
        $this->as($this->centreEmail())->postJson("/api/partner/installation-requests/{$id}/offer", ['price' => 2000, 'eta_days' => 1])->assertCreated();
        $offer = $this->as(self::CUSTOMER)->getJson("/api/installation-requests/{$id}")->json('offers.0.id');
        $this->postJson("/api/installation-requests/{$id}/accept", ['offer_id' => $offer, 'accept_terms' => true])->assertOk();
        $this->postJson("/api/installation-requests/{$id}/pay")->assertOk();

        return $id;
    }

    public function test_a_centre_sets_its_own_rules_within_limits(): void
    {
        $this->as($this->centreEmail());
        $this->getJson('/api/partner/settings')->assertOk()->assertJsonPath('service_radius_km', 100)->assertJsonPath('accepts_requests', true);

        $this->putJson('/api/partner/settings', ['service_radius_km' => 15, 'daily_capacity' => 3, 'bays' => 2, 'job_hours' => 4, 'min_job_price' => 800, 'work_types' => ['install']])
            ->assertOk()->assertJsonPath('service_radius_km', 15)->assertJsonPath('work_types', ['install']);

        foreach ([['service_radius_km' => 500], ['service_radius_km' => 0], ['daily_capacity' => 0], ['bays' => 99], ['job_hours' => 20], ['min_job_price' => -1], ['work_types' => ['sell-cars']]] as $bad) {
            $this->putJson('/api/partner/settings', $bad)->assertUnprocessable();
        }

        // Only the centre's own manager reaches this: a customer cannot.
        $this->as(self::CUSTOMER)->getJson('/api/partner/settings')->assertForbidden();
    }

    public function test_opening_hours_and_days_off_are_validated(): void
    {
        $this->as($this->centreEmail());

        $week = $this->putJson('/api/partner/hours', ['days' => [['weekday' => 0, 'opens' => '10:00', 'closes' => '16:00'], ['weekday' => 1, 'opens' => '10:00', 'closes' => '16:00']]])->assertOk()->json('hours');
        $this->assertSame([0, 1], array_column($week, 'weekday'));                  // every day not sent is closed

        $this->putJson('/api/partner/hours', ['days' => [['weekday' => 0, 'opens' => '16:00', 'closes' => '10:00']]])->assertUnprocessable();    // closes before it opens
        $this->putJson('/api/partner/hours', ['days' => [['weekday' => 0, 'opens' => '25:00', 'closes' => '26:00']]])->assertUnprocessable();
        $this->putJson('/api/partner/hours', ['days' => [['weekday' => 0, 'opens' => '09:00', 'closes' => '12:00'], ['weekday' => 0, 'opens' => '13:00', 'closes' => '17:00']]])->assertUnprocessable();   // a day twice
        $this->putJson('/api/partner/hours', ['days' => [['weekday' => 9, 'opens' => '09:00', 'closes' => '12:00']]])->assertUnprocessable();

        $off = $this->postJson('/api/partner/time-off', ['date' => '2026-12-10', 'reason' => '<i>Holiday</i>'])->assertCreated()->json('time_off');
        $this->assertSame('Holiday', $off[0]['reason']);
        $this->postJson('/api/partner/time-off', ['date' => '2020-01-01'])->assertUnprocessable();                                              // the past
        $this->deleteJson('/api/partner/time-off/'.$off[0]['id'])->assertOk()->assertJsonCount(0, 'time_off');
    }

    public function test_a_centre_that_is_off_or_does_not_do_the_work_is_not_asked(): void
    {
        $asked = function (): bool {
            $id = $this->request();

            return in_array($id, array_column($this->as($this->centreEmail())->getJson('/api/partner/installation-requests')->json(), 'id'), true);
        };
        $this->assertTrue($asked());

        $this->as($this->centreEmail())->putJson('/api/partner/settings', ['accepts_requests' => false])->assertOk();
        $this->assertFalse($asked());

        $this->putJson('/api/partner/settings', ['accepts_requests' => true, 'work_types' => ['repair']])->assertOk();
        $this->assertFalse($asked());                                    // asked to install, only repairs
        $this->putJson('/api/partner/settings', ['work_types' => ['install', 'repair']])->assertOk();
        $this->assertTrue($asked());

        // A short radius leaves out a job further away than the centre travels.
        $this->putJson('/api/partner/settings', ['service_radius_km' => 1])->assertOk();
        $far = $this->request(['latitude' => 30.2, 'longitude' => 31.5, 'city' => 'Elsewhere']);
        $this->assertNotContains($far, array_column($this->as($this->centreEmail())->getJson('/api/partner/installation-requests')->json(), 'id'));
    }

    public function test_an_offer_below_the_centres_minimum_is_refused(): void
    {
        $id = $this->request();
        $this->as($this->centreEmail())->putJson('/api/partner/settings', ['min_job_price' => 1500])->assertOk();

        $this->postJson("/api/partner/installation-requests/{$id}/offer", ['price' => 1000, 'eta_days' => 1])->assertUnprocessable()->assertJsonValidationErrors('price');
        $this->postJson("/api/partner/installation-requests/{$id}/offer", ['price' => 1500, 'eta_days' => 1])->assertCreated();
    }

    public function test_the_customer_books_a_time_the_centre_really_offers(): void
    {
        $id = $this->paidJob();
        $this->as(self::CUSTOMER);

        $slots = $this->getJson("/api/installation-requests/{$id}/slots")->assertOk()->json();
        $this->assertSame('Africa/Cairo', $slots['timezone']);
        $times = collect($slots['days'])->flatMap(fn ($d) => $d['times']);
        $this->assertNotEmpty($times);
        // The market's weekend (Friday and Saturday) is closed; nothing is offered inside the notice period.
        foreach ($slots['days'] as $day) {
            $this->assertNotContains(CarbonImmutable::parse($day['date'])->dayOfWeek, [5, 6], $day['date']);
        }
        $this->assertTrue($times->every(fn ($t) => CarbonImmutable::parse($t)->gte(now()->addHours(12))));

        $start = $times->first();
        $this->postJson("/api/installation-requests/{$id}/booking", ['starts_at' => $start])->assertCreated()->assertJsonPath('booking.status', 'booked');
        $this->postJson("/api/installation-requests/{$id}/booking", ['starts_at' => $times->last()])->assertUnprocessable();               // already has a time

        // A time the centre never offered is refused (3 a.m., a Friday, the past).
        $this->putJson("/api/installation-requests/{$id}/booking", ['starts_at' => '2026-12-09T01:00:00Z'])->assertUnprocessable();
        $this->putJson("/api/installation-requests/{$id}/booking", ['starts_at' => '2026-12-11T09:00:00Z'])->assertUnprocessable();
        $this->putJson("/api/installation-requests/{$id}/booking", ['starts_at' => '2020-12-09T09:00:00Z'])->assertUnprocessable();
    }

    public function test_a_booked_time_is_taken_and_a_bay_count_allows_overlap(): void
    {
        $first = $this->paidJob();
        $times = collect($this->as(self::CUSTOMER)->getJson("/api/installation-requests/{$first}/slots")->json('days'))->flatMap(fn ($d) => $d['times']);
        $start = $times->first();
        $this->postJson("/api/installation-requests/{$first}/booking", ['starts_at' => $start])->assertCreated();

        // One bay: the same time is gone for the next customer of this centre.
        $second = $this->paidJob();
        $left = collect($this->getJson("/api/installation-requests/{$second}/slots")->json('days'))->flatMap(fn ($d) => $d['times']);
        $this->assertNotContains($start, $left->all());
        $this->postJson("/api/installation-requests/{$second}/booking", ['starts_at' => $start])->assertUnprocessable();

        // Two bays: the same time is open again.
        $this->as($this->centreEmail())->putJson('/api/partner/settings', ['bays' => 2])->assertOk();
        $this->as(self::CUSTOMER)->postJson("/api/installation-requests/{$second}/booking", ['starts_at' => $start])->assertCreated();

        // The daily capacity stops a third job on a day that is full.
        $this->as($this->centreEmail())->putJson('/api/partner/settings', ['bays' => 5, 'daily_capacity' => 2])->assertOk();
        $third = $this->paidJob();
        $day = CarbonImmutable::parse($start)->setTimezone('Africa/Cairo')->toDateString();
        $offered = collect($this->as(self::CUSTOMER)->getJson("/api/installation-requests/{$third}/slots")->json('days'))->pluck('date')->all();
        $this->assertNotContains($day, $offered);
    }

    public function test_a_day_off_is_not_offered(): void
    {
        $id = $this->paidJob();
        $firstDay = collect($this->as(self::CUSTOMER)->getJson("/api/installation-requests/{$id}/slots")->json('days'))->first()['date'];

        $this->as($this->centreEmail())->postJson('/api/partner/time-off', ['date' => $firstDay])->assertCreated();
        $days = collect($this->as(self::CUSTOMER)->getJson("/api/installation-requests/{$id}/slots")->json('days'))->pluck('date')->all();
        $this->assertNotContains($firstDay, $days);
    }

    public function test_a_booking_can_be_moved_twice_and_not_close_to_the_start(): void
    {
        $id = $this->paidJob();
        $this->as(self::CUSTOMER);
        $times = fn () => collect($this->getJson("/api/installation-requests/{$id}/slots")->json('days'))->flatMap(fn ($d) => $d['times'])->values();
        $this->postJson("/api/installation-requests/{$id}/booking", ['starts_at' => $times()->get(30)])->assertCreated();

        $this->putJson("/api/installation-requests/{$id}/booking", ['starts_at' => $times()->get(40)])->assertOk()->assertJsonPath('booking.reschedules', 1);
        $this->putJson("/api/installation-requests/{$id}/booking", ['starts_at' => $times()->get(41)])->assertOk()->assertJsonPath('booking.reschedules', 2);
        $this->putJson("/api/installation-requests/{$id}/booking", ['starts_at' => $times()->get(42)])->assertUnprocessable();       // only twice

        // Within 24 hours of the start nothing can be changed any more.
        InstallationBooking::where('request_id', $id)->update(['starts_at' => now()->addHours(5), 'ends_at' => now()->addHours(8), 'reschedules' => 0]);
        $this->putJson("/api/installation-requests/{$id}/booking", ['starts_at' => $times()->get(10)])->assertUnprocessable();
    }

    public function test_the_centre_sees_its_calendar_and_can_hand_a_time_back(): void
    {
        $id = $this->paidJob();
        $start = collect($this->as(self::CUSTOMER)->getJson("/api/installation-requests/{$id}/slots")->json('days'))->flatMap(fn ($d) => $d['times'])->first();
        $this->postJson("/api/installation-requests/{$id}/booking", ['starts_at' => $start])->assertCreated();

        $this->as($this->centreEmail());
        $calendar = $this->getJson('/api/partner/calendar')->assertOk()->json();
        $this->assertCount(1, $calendar);
        $this->assertSame($id, $calendar[0]['job_id']);
        $this->assertSame('Nour', $calendar[0]['customer']);                  // a first name, nothing more
        $this->assertArrayNotHasKey('phone', $calendar[0]);

        // Another centre sees none of it.
        $other = $this->centreEmail('Zamalek Detailing Studio');
        $this->assertSame([], $this->as($other)->getJson('/api/partner/calendar')->json());
        $this->postJson("/api/partner/installation-jobs/{$id}/booking/cancel", ['reason' => 'x'])->assertNotFound();

        $this->as($this->centreEmail())->postJson("/api/partner/installation-jobs/{$id}/booking/cancel", ['reason' => 'A technician is ill'])->assertOk();
        $this->assertSame([], $this->getJson('/api/partner/calendar')->json());

        // The customer is asked to choose again and can.
        $this->as(self::CUSTOMER)->getJson("/api/installation-requests/{$id}/booking")->assertJsonPath('booking.status', 'cancelled')->assertJsonPath('booking.cancel_reason', 'A technician is ill');
        $this->postJson("/api/installation-requests/{$id}/booking", ['starts_at' => $start])->assertCreated()->assertJsonPath('booking.status', 'booked');
    }

    public function test_only_the_customer_of_a_paid_job_can_book(): void
    {
        $id = $this->request();
        $this->as(self::CUSTOMER)->getJson("/api/installation-requests/{$id}/slots")->assertUnprocessable();             // nothing paid yet

        $paid = $this->paidJob();
        $stranger = User::role('customer')->where('email', '!=', self::CUSTOMER)->first();
        $this->actingAs($stranger, 'sanctum')->getJson("/api/installation-requests/{$paid}/slots")->assertNotFound();
        $this->postJson("/api/installation-requests/{$paid}/booking", ['starts_at' => now()->addDays(2)->toIso8601String()])->assertNotFound();
    }
}
