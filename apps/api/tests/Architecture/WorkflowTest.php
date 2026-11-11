<?php

namespace Tests\Architecture;

use App\Core\Modules\ModuleRegistry;
use App\Core\Workflow\TransitionNotAllowed;
use App\Core\Workflow\WorkflowContext;
use App\Core\Workflow\WorkflowDefinition;
use App\Core\Workflow\WorkflowEngine;
use App\Core\Workflow\WorkflowRegistry;
use App\Enums\OrderStatus;
use App\Models\Tenant;
use App\Products\Armorhub\Workflows\OrderWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\Clinic\ClinicModule;
use Tests\Fixtures\Clinic\SendReminder;
use Tests\Fixtures\Clinic\WithinHours;
use Tests\TestCase;

/** Workflows are data. A second product defines its own lifecycle without touching the core, and a market can re-wire one. */
class WorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(ModuleRegistry::class)->load([ClinicModule::class]);
        SendReminder::$ran = [];
        WithinHours::$open = true;
    }

    private function plan(string $current, string $transition, ?int $tenantId = null)
    {
        return app(WorkflowEngine::class)->plan('clinic.appointment', $current, $transition, new WorkflowContext(new \stdClass(), null, $tenantId));
    }

    public function test_a_second_product_defines_a_lifecycle_by_data_only(): void
    {
        $planned = $this->plan('booked', 'confirm');
        $this->assertSame('confirmed', $planned->to);
        $planned->runActions();
        $this->assertSame(['confirm:booked->confirmed'], SendReminder::$ran);

        $this->assertSame(['confirm', 'cancel'], app(WorkflowEngine::class)->available('clinic.appointment', null, 'booked'));
        $this->assertSame([], app(WorkflowEngine::class)->available('clinic.appointment', null, 'completed'));
    }

    public function test_moves_must_follow_the_definition(): void
    {
        foreach ([['booked', 'check_in'], ['completed', 'cancel'], ['booked', 'teleport']] as [$state, $step]) {
            try {
                $this->plan($state, $step);
                $this->fail("{$state} -> {$step} was allowed");
            } catch (TransitionNotAllowed) {
                $this->addToAssertionCount(1);
            }
        }

        try {
            $this->plan('checked_in', 'cancel');
        } catch (TransitionNotAllowed $e) {
            $this->assertSame('Too late to cancel: please call the clinic.', $e->getMessage());   // the definition owns the wording
        }
    }

    public function test_guards_can_refuse_with_a_reason(): void
    {
        $this->assertSame('checked_in', $this->plan('confirmed', 'check_in')->to);

        WithinHours::$open = false;
        $this->expectExceptionMessage('The clinic is closed.');
        $this->plan('confirmed', 'check_in');
    }

    public function test_a_malformed_definition_is_refused(): void
    {
        $bad = [
            ['key' => 'x', 'initial' => 'a', 'states' => ['a' => []], 'transitions' => []],                                           // key without a dot
            ['key' => 'p.q', 'initial' => 'nope', 'states' => ['a' => []], 'transitions' => []],                                      // initial not a state
            ['key' => 'p.q', 'initial' => 'a', 'states' => ['a' => []], 'transitions' => ['go' => ['from' => ['a'], 'to' => 'b']]],  // goes to nowhere
            ['key' => 'p.q', 'initial' => 'a', 'states' => ['a' => []], 'transitions' => ['go' => ['from' => ['z'], 'to' => 'a']]],  // starts from nowhere
        ];

        foreach ($bad as $definition) {
            try {
                WorkflowDefinition::fromArray($definition);
                $this->fail('A malformed definition was accepted.');
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_a_market_can_rewire_transitions_without_changing_the_product(): void
    {
        $a = Tenant::create(['name' => 'Clinic A']);
        $b = Tenant::create(['name' => 'Clinic B']);
        $registry = app(WorkflowRegistry::class);

        // Clinic A allows cancelling even after check-in.
        $custom = $registry->default('clinic.appointment')->toArray();
        $custom['transitions']['cancel']['from'] = ['booked', 'confirmed', 'checked_in'];
        $this->assertSame([], $registry->overrideErrors('clinic.appointment', $custom));
        \App\Core\Workflow\Models\WorkflowOverride::create(['workflow_key' => 'clinic.appointment', 'tenant_id' => $a->id, 'version' => 1, 'definition' => $custom, 'active' => true]);
        $registry->forget();

        $this->assertSame('cancelled', $this->plan('checked_in', 'cancel', $a->id)->to);   // A: allowed
        $this->expectException(TransitionNotAllowed::class);
        $this->plan('checked_in', 'cancel', $b->id);                                         // B: still the product's default
    }

    public function test_overrides_cannot_break_what_existing_records_depend_on(): void
    {
        $registry = app(WorkflowRegistry::class);
        $base = $registry->default('clinic.appointment')->toArray();

        $newState = $base;
        $newState['states']['archived'] = ['label' => 'Archived'];
        $this->assertStringContainsString('states cannot change', implode(' ', $registry->overrideErrors('clinic.appointment', $newState)));

        $removed = $base;
        unset($removed['states']['cancelled'], $removed['transitions']['cancel']);
        $this->assertNotEmpty($registry->overrideErrors('clinic.appointment', $removed));

        $unknownGuard = $base;
        $unknownGuard['transitions']['confirm']['guards'] = ['made.up'];
        $this->assertStringContainsString('unknown guard', implode(' ', $registry->overrideErrors('clinic.appointment', $unknownGuard)));

        $unknownAction = $base;
        $unknownAction['transitions']['confirm']['actions'] = ['made.up'];
        $this->assertStringContainsString('unknown action', implode(' ', $registry->overrideErrors('clinic.appointment', $unknownAction)));

        $movedStart = $base;
        $movedStart['initial'] = 'confirmed';
        $this->assertNotEmpty($registry->overrideErrors('clinic.appointment', $movedStart));
    }

    public function test_the_armorhub_order_workflow_matches_the_order_status_enum(): void
    {
        $states = array_keys(OrderWorkflow::definition()['states']);
        $enum = array_map(fn ($c) => $c->value, OrderStatus::cases());
        sort($states);
        sort($enum);

        $this->assertSame($enum, $states, 'The workflow and the OrderStatus enum describe different states.');
    }
}
