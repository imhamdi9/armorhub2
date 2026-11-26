<?php

namespace Tests\Fixtures\Clinic;

use App\Core\Blueprints\BlueprintContext;
use App\Core\Blueprints\BlueprintStepHandler;

class ExplodeStep implements BlueprintStepHandler
{
    public function type(): string
    {
        return 'clinic.explode';
    }

    public function apply(array $args, BlueprintContext $context): array
    {
        throw new \RuntimeException('something went wrong half way');
    }
}
