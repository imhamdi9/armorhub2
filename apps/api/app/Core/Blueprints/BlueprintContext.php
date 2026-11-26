<?php

namespace App\Core\Blueprints;

/** What a step can see while a blueprint is applied: the operator's answers and what earlier steps created. */
final class BlueprintContext
{
    /** @param array<string, mixed> $params */
    public function __construct(public readonly array $params, public readonly ?int $actorId = null, public array $outputs = [], public ?int $tenantId = null)
    {
    }
}
