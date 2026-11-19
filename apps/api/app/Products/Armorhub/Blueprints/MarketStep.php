<?php

namespace App\Products\Armorhub\Blueprints;

use App\Core\Blueprints\BlueprintContext;
use App\Core\Blueprints\BlueprintError;
use App\Core\Blueprints\BlueprintStepHandler;
use App\Models\Industry;
use App\Models\Tenant;
use App\Support\Markets\Markets;

/** "armorhub.market": create a market (a tenant) for one industry in one country. Args: name, industry, country. */
class MarketStep implements BlueprintStepHandler
{
    public function __construct(private Markets $countries)
    {
    }

    public function type(): string
    {
        return 'armorhub.market';
    }

    public function apply(array $args, BlueprintContext $context): array
    {
        $industry = Industry::where('key', $args['industry'] ?? '')->first() ?? throw new BlueprintError("Unknown industry [".($args['industry'] ?? '')."].");
        $country = strtoupper((string) ($args['country'] ?? ''));

        if ($country !== '' && ! $this->countries->get($country)) {
            throw new BlueprintError("Unknown country [{$country}].");
        }

        $tenant = Tenant::create(['name' => (string) ($args['name'] ?? throw new BlueprintError('A market needs a name.')), 'industry_id' => $industry->id, 'market' => $country ?: null]);

        return ['tenant_id' => $tenant->id, 'name' => $tenant->name];
    }
}
