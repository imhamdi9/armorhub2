<?php

namespace Tests\Architecture;

use App\Core\Events\Event;
use App\Core\Events\EventBus;
use App\Core\Events\InvalidEventPayload;
use App\Core\Events\Models\DeliveryRecord;
use App\Core\Events\Models\EventRecord;
use App\Core\Events\UnknownEvent;
use App\Core\Modules\ModuleRegistry;
use App\Core\Modules\TenantModules;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\Clinic\BookingReactor;
use Tests\Fixtures\Clinic\ClinicModule;
use Tests\Fixtures\Clinic\FlakyReactor;
use Tests\TestCase;

/**
 * The proof that the platform is generic: a second, unrelated product (a clinic) is added entirely from the outside.
 * Nothing under app/Core or app/Products/Armorhub changes for it to work.
 */
class SecondProductTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $clinicA;
    private Tenant $clinicB;

    protected function setUp(): void
    {
        parent::setUp();
        app(ModuleRegistry::class)->load([ClinicModule::class]);
        BookingReactor::$seen = [];
        FlakyReactor::$broken = false;
        FlakyReactor::$calls = 0;
        $this->clinicA = Tenant::create(['name' => 'Clinic A']);
        $this->clinicB = Tenant::create(['name' => 'Clinic B']);
    }

    private function book(?Tenant $tenant = null, int $id = 1)
    {
        return app(EventBus::class)->publish(new Event('clinic.appointment_booked', ['appointment_id' => $id, 'patient' => 'Sara'], ($tenant ?? $this->clinicA)->id, 'appointment', $id, 'user', 7));
    }

    private function delivery(string $eventId, string $subscriber): DeliveryRecord
    {
        return DeliveryRecord::whereHas('event', fn ($q) => $q->where('event_id', $eventId))->where('subscriber', $subscriber)->firstOrFail();
    }

    public function test_a_second_product_publishes_and_reacts_with_no_core_changes(): void
    {
        $envelope = $this->book();

        $this->assertSame('delivered', $this->delivery($envelope->id, 'clinic.booking-reactor')->status);
        $this->assertSame($envelope->id, BookingReactor::$seen[0]->id);
        $this->assertSame($this->clinicA->id, BookingReactor::$seen[0]->tenantId);
        $this->assertSame('clinic', $envelope->source);                 // owned by the module that declared it
        $this->assertSame('user', $envelope->actorType);

        // ArmorHub's own subscriber never saw a clinic event: subscriptions are by pattern.
        $this->assertSame(0, DeliveryRecord::where('subscriber', 'armorhub.order-notifications')->count());
    }

    public function test_events_published_by_a_subscriber_are_linked_back_to_the_event_that_caused_them(): void
    {
        $booking = $this->book();

        $reminder = EventRecord::where('name', 'clinic.reminder_scheduled')->firstOrFail();
        $this->assertSame($booking->correlationId, $reminder->correlation_id);   // one story
        $this->assertSame($booking->id, $reminder->causation_id);                 // caused by the booking
    }

    public function test_events_are_a_governed_contract(): void
    {
        $bus = app(EventBus::class);

        $this->expectException(UnknownEvent::class);
        $bus->publish(new Event('clinic.not_declared', []));
    }

    public function test_payloads_must_match_the_declared_schema(): void
    {
        $bus = app(EventBus::class);
        $bad = [
            ['patient' => 'Sara'],                                                       // missing required field
            ['appointment_id' => 'one', 'patient' => 'Sara'],                            // wrong type
            ['appointment_id' => 1, 'patient' => 'Sara', 'surprise' => true],            // undeclared field
        ];

        foreach ($bad as $payload) {
            try {
                $bus->publish(new Event('clinic.appointment_booked', $payload));
                $this->fail('An invalid payload was accepted.');
            } catch (InvalidEventPayload) {
                $this->addToAssertionCount(1);
            }
        }

        $bus->publish(new Event('clinic.appointment_booked', ['appointment_id' => 1, 'patient' => 'Sara', 'notes' => null])); // optional may be null
        $this->assertSame(1, EventRecord::where('name', 'clinic.appointment_booked')->count());
    }

    public function test_the_event_is_written_with_the_change_and_never_sent_if_it_rolls_back(): void
    {
        try {
            DB::transaction(function () {
                $this->book();
                throw new \RuntimeException('the business change failed');
            });
        } catch (\RuntimeException) {
        }

        $this->assertSame(0, EventRecord::count());
        $this->assertSame([], BookingReactor::$seen);
    }

    public function test_delivery_waits_for_the_transaction_to_commit(): void
    {
        $inside = null;

        DB::transaction(function () use (&$inside) {
            $envelope = $this->book();
            $inside = $this->delivery($envelope->id, 'clinic.booking-reactor')->status;
        });

        $this->assertSame('pending', $inside);
        $this->assertSame('delivered', DeliveryRecord::where('subscriber', 'clinic.booking-reactor')->first()->status);
    }

    public function test_a_failing_subscriber_is_retried_then_dead_lettered_without_hurting_the_publisher(): void
    {
        config(['events.max_attempts' => 3]);
        FlakyReactor::$broken = true;

        $envelope = $this->book();                                    // the publisher is not affected
        $d = $this->delivery($envelope->id, 'clinic.flaky');
        $this->assertSame('failed', $d->status);
        $this->assertSame(1, $d->attempts);
        $this->assertStringContainsString('unavailable', $d->last_error);
        $this->assertNotNull($d->next_attempt_at);
        $this->assertSame('delivered', $this->delivery($envelope->id, 'clinic.booking-reactor')->status);   // others still run

        // Not due yet: nothing happens. Once due, it retries.
        $this->artisan('events:process')->assertSuccessful();
        $this->assertSame(1, $d->fresh()->attempts);
        $this->travel(2)->minutes();
        $this->artisan('events:process')->assertSuccessful();
        $this->assertSame(2, $d->fresh()->attempts);
        $this->travel(10)->minutes();
        $this->artisan('events:process')->assertSuccessful();
        $this->assertSame('dead', $d->fresh()->status);                // dead-lettered after the 3rd failure

        // Fix the downstream system, then replay just that subscriber.
        FlakyReactor::$broken = false;
        $this->artisan('events:replay', ['event' => $envelope->id, '--subscriber' => 'clinic.flaky'])->assertSuccessful();
        $this->assertSame('delivered', $d->fresh()->status);
        $this->assertNull($d->fresh()->last_error);
    }

    public function test_a_tenant_can_switch_a_module_off_and_its_events_are_skipped_not_lost(): void
    {
        app(TenantModules::class)->set($this->clinicA->id, 'clinic', false);

        $off = $this->book($this->clinicA, 1);
        $on = $this->book($this->clinicB, 2);

        $this->assertSame('skipped', $this->delivery($off->id, 'clinic.booking-reactor')->status);
        $this->assertSame('delivered', $this->delivery($on->id, 'clinic.booking-reactor')->status);
        $this->assertCount(1, BookingReactor::$seen);                  // only clinic B's booking was reacted to

        // The event itself is kept; switch the module back on and replay.
        app(TenantModules::class)->set($this->clinicA->id, 'clinic', true);
        app(EventBus::class)->replay($off->id);
        $this->assertSame('delivered', $this->delivery($off->id, 'clinic.booking-reactor')->status);
    }

    public function test_modules_must_fit_the_core_and_each_other(): void
    {
        $fresh = fn () => new ModuleRegistry(new \App\Core\Events\EventCatalog(), new \App\Core\Events\SubscriberRegistry());

        // Two modules cannot claim the same event.
        $a = new class implements \App\Core\Modules\Module {
            public function manifest(): \App\Core\Modules\ModuleManifest { return new \App\Core\Modules\ModuleManifest('a', 'A', '1'); }
            public function register(\App\Core\Modules\ModuleContext $c): void { $c->event('shared.thing_happened'); }
        };
        $b = new class implements \App\Core\Modules\Module {
            public function manifest(): \App\Core\Modules\ModuleManifest { return new \App\Core\Modules\ModuleManifest('b', 'B', '1'); }
            public function register(\App\Core\Modules\ModuleContext $c): void { $c->event('shared.thing_happened'); }
        };
        $this->app->instance('mod.a', $a);
        $this->app->instance('mod.b', $b);
        try {
            $fresh()->load(['mod.a', 'mod.b']);
            $this->fail('Two modules owned one event.');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('already owned', $e->getMessage());
        }

        // A module that needs a newer core, or a module that is not installed, is refused.
        $needy = new class implements \App\Core\Modules\Module {
            public function manifest(): \App\Core\Modules\ModuleManifest { return new \App\Core\Modules\ModuleManifest('needy', 'Needy', '1', dependsOn: ['absent']); }
            public function register(\App\Core\Modules\ModuleContext $c): void {}
        };
        $future = new class implements \App\Core\Modules\Module {
            public function manifest(): \App\Core\Modules\ModuleManifest { return new \App\Core\Modules\ModuleManifest('future', 'Future', '1', minCoreVersion: 999); }
            public function register(\App\Core\Modules\ModuleContext $c): void {}
        };
        $this->app->instance('mod.needy', $needy);
        $this->app->instance('mod.future', $future);
        $this->expectExceptionMessage('not installed');
        try {
            $fresh()->load(['mod.future']);
            $this->fail('A module needing a newer core was accepted.');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('needs core version', $e->getMessage());
        }
        $fresh()->load(['mod.needy']);
    }
}
