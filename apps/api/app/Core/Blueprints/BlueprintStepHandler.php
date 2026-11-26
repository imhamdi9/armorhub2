<?php

namespace App\Core\Blueprints;

/** Performs one kind of step. Runs inside the blueprint's transaction, so a failure undoes everything. */
interface BlueprintStepHandler
{
    /** The step type this handles, e.g. "armorhub.market". */
    public function type(): string;

    /**
     * @param array<string, mixed> $args  with placeholders already filled in
     * @return array<string, mixed>       outputs later steps may refer to; a "tenant_id" output becomes the blueprint's tenant
     */
    public function apply(array $args, BlueprintContext $context): array;
}
