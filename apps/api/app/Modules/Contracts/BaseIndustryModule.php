<?php

namespace App\Modules\Contracts;

/**
 * Sensible defaults so a minimal module only declares identity.
 */
abstract class BaseIndustryModule implements IndustryModule
{
    public function description(): string
    {
        return '';
    }

    public function subjectModels(): array
    {
        return [];
    }

    public function targetModels(): array
    {
        return [];
    }

    public function policies(): array
    {
        return [];
    }

    public function routesFile(): ?string
    {
        return null;
    }

    public function permissions(): array
    {
        return [];
    }

    public function pricingStrategy(): ?PricingStrategy
    {
        return null;
    }
}
