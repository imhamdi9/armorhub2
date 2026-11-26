<?php

namespace App\Products\Armorhub\Blueprints;

use App\Core\Blueprints\BlueprintContext;
use App\Core\Blueprints\BlueprintError;
use App\Core\Blueprints\BlueprintStepHandler;
use App\Modules\IndustryRegistry;

/**
 * "armorhub.catalog": make sure shared catalog entries exist (vehicle parts, room types...). Args: type (an alias the
 * industry modules declare), items: list of attribute arrays that include a "name". Existing entries are left alone.
 */
class CatalogStep implements BlueprintStepHandler
{
    public function __construct(private IndustryRegistry $industries)
    {
    }

    public function type(): string
    {
        return 'armorhub.catalog';
    }

    public function apply(array $args, BlueprintContext $context): array
    {
        $alias = (string) ($args['type'] ?? '');
        $class = ($this->industries->targetModels() + $this->industries->catalogModels() + $this->industries->subjectModels())[$alias] ?? throw new BlueprintError("Unknown catalog [{$alias}].");
        $created = 0;

        foreach ($args['items'] ?? [] as $attrs) {
            $name = $attrs['name'] ?? throw new BlueprintError('A catalog item needs a name.');

            $class::firstOrCreate(['name' => $name], $attrs) ->wasRecentlyCreated && $created++;
        }

        return ['type' => $alias, 'created' => $created];
    }
}
