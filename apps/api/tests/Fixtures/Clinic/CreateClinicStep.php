<?php

namespace Tests\Fixtures\Clinic;

use App\Core\Blueprints\BlueprintContext;
use App\Core\Blueprints\BlueprintStepHandler;
use App\Models\Tenant;

/** The clinic's own step type: creates its tenant. Lives outside the core. */
class CreateClinicStep implements BlueprintStepHandler
{
    public function type(): string
    {
        return 'clinic.create_clinic';
    }

    public function apply(array $args, BlueprintContext $context): array
    {
        $tenant = Tenant::create(['name' => $args['name']]);

        return ['tenant_id' => $tenant->id];
    }
}
