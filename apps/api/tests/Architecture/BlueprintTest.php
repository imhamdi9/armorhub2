<?php

namespace Tests\Architecture;

use App\Core\Blueprints\BlueprintApplier;
use App\Core\Blueprints\BlueprintError;
use App\Core\Blueprints\Models\BlueprintApplication;
use App\Core\Events\Models\EventRecord;
use App\Core\Modules\ModuleRegistry;
use App\Core\Workflow\WorkflowContext;
use App\Core\Workflow\WorkflowEngine;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\Clinic\ClinicModule;
use Tests\TestCase;

/** A second product ships its own launch bundle, using one step of its own and one from the core, with no core change. */
class BlueprintTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(ModuleRegistry::class)->load([ClinicModule::class]);
    }

    private function apply(string $id, array $params)
    {
        return app(BlueprintApplier::class)->apply($id, $params);
    }

    public function test_a_blueprint_launches_a_business_by_configuration(): void
    {
        $app = $this->apply('clinic.small-clinic', ['name' => 'Nile Clinic']);

        $tenant = Tenant::find($app->tenant_id);
        $this->assertSame('Nile Clinic', $tenant->name);
        $this->assertSame($tenant->id, $app->outputs['clinic']['tenant_id']);

        // The core's workflow step customised the new clinic's lifecycle; other clinics keep the default.
        $engine = app(WorkflowEngine::class);
        $this->assertSame('cancelled', $engine->plan('clinic.appointment', 'checked_in', 'cancel', new WorkflowContext(new \stdClass(), null, $tenant->id))->to);
        $other = Tenant::create(['name' => 'Other']);
        $this->expectException(\App\Core\Workflow\TransitionNotAllowed::class);
        $engine->plan('clinic.appointment', 'checked_in', 'cancel', new WorkflowContext(new \stdClass(), null, $other->id));
    }

    public function test_applying_announces_an_event_and_is_idempotent(): void
    {
        $first = $this->apply('clinic.small-clinic', ['name' => 'Nile Clinic']);
        $again = $this->apply('clinic.small-clinic', ['name' => 'Nile Clinic']);

        $this->assertSame($first->id, $again->id);                        // same answers: same result, nothing duplicated
        $this->assertSame(1, Tenant::where('name', 'Nile Clinic')->count());
        $this->assertSame(1, EventRecord::where('name', 'blueprint.applied')->count());
        $this->assertSame('core', EventRecord::where('name', 'blueprint.applied')->first()->source);

        $this->apply('clinic.small-clinic', ['name' => 'Delta Clinic']);   // different answers: a new clinic
        $this->assertSame(2, BlueprintApplication::count());
    }

    public function test_a_failure_half_way_undoes_everything(): void
    {
        try {
            $this->apply('clinic.broken', ['name' => 'Ghost Clinic']);
            $this->fail('The broken blueprint succeeded.');
        } catch (\RuntimeException) {
        }

        $this->assertSame(0, Tenant::where('name', 'Ghost Clinic')->count());   // the first step was rolled back with the second
        $this->assertSame(0, BlueprintApplication::count());
        $this->assertSame(0, EventRecord::where('name', 'blueprint.applied')->count());
    }

    public function test_the_answers_are_checked(): void
    {
        foreach ([[], ['name' => ''], ['name' => 'X', 'surprise' => 'y']] as $bad) {
            try {
                $this->apply('clinic.small-clinic', $bad);
                $this->fail('Bad answers were accepted: '.json_encode($bad));
            } catch (BlueprintError) {
                $this->addToAssertionCount(1);
            }
        }

        $this->expectException(BlueprintError::class);
        $this->apply('clinic.does-not-exist', ['name' => 'X']);
    }

    public function test_placeholders_keep_their_type_and_refer_to_earlier_steps(): void
    {
        $app = $this->apply('clinic.small-clinic', ['name' => 'Typed Clinic', 'city' => 'Cairo']);
        $this->assertSame('Cairo', $app->params['city']);
        $this->assertIsInt($app->tenant_id);                              // a tenant_id output becomes the blueprint's tenant
    }
}
