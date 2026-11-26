<?php

namespace App\Products\Armorhub\Blueprints;

use App\Core\Blueprints\BlueprintContext;
use App\Core\Blueprints\BlueprintError;
use App\Core\Blueprints\BlueprintStepHandler;
use App\Models\Company;

/** "armorhub.store": the market's public storefront, switched on. Args: name, headline, subheadline, about, color. */
class StoreStep implements BlueprintStepHandler
{
    public function type(): string
    {
        return 'armorhub.store';
    }

    public function apply(array $args, BlueprintContext $context): array
    {
        $tenantId = $context->tenantId ?? throw new BlueprintError('A step before "armorhub.store" must create the market.');
        $color = $args['color'] ?? '#163300';

        if (! preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            throw new BlueprintError('The brand colour must look like #163300.');
        }

        $company = Company::create([
            'tenant_id' => $tenantId, 'name' => (string) ($args['name'] ?? throw new BlueprintError('A store needs a name.')), 'type' => 'platform', 'status' => 'active',
            'settings' => ['storefront' => [
                'enabled' => true, 'headline' => $args['headline'] ?? null, 'subheadline' => $args['subheadline'] ?? null, 'about' => $args['about'] ?? null,
                'primary_color' => $color, 'featured_product_ids' => [],
            ]],
        ]);

        return ['company_id' => $company->id, 'slug' => $company->slug];
    }
}
