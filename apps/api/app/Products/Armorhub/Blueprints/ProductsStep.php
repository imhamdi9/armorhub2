<?php

namespace App\Products\Armorhub\Blueprints;

use App\Core\Blueprints\BlueprintContext;
use App\Core\Blueprints\BlueprintError;
use App\Core\Blueprints\BlueprintStepHandler;
use App\Models\Product;
use App\Models\ProductPriceRule;
use App\Models\Tenant;
use App\Modules\IndustryRegistry;
use App\Support\Markets\Markets;
use Illuminate\Support\Str;

/** "armorhub.products": the market's starting products, with prices per catalog option. Args: items. */
class ProductsStep implements BlueprintStepHandler
{
    public function __construct(private IndustryRegistry $industries, private Markets $countries)
    {
    }

    public function type(): string
    {
        return 'armorhub.products';
    }

    public function apply(array $args, BlueprintContext $context): array
    {
        $tenant = Tenant::find($context->tenantId) ?? throw new BlueprintError('A step before "armorhub.products" must create the market.');
        $targets = $this->industries->targetModels();
        $made = [];

        foreach ($args['items'] ?? [] as $item) {
            $name = $item['name'] ?? throw new BlueprintError('A product needs a name.');

            $product = Product::create([
                'industry_id' => $tenant->industry_id, 'market' => $tenant->market, 'name' => $name, 'brand' => $item['brand'] ?? null, 'category' => $item['category'] ?? null,
                'slug' => Str::slug($name).'-'.$tenant->id, 'base_price' => $item['base_price'] ?? 0, 'warranty_years' => $item['warranty_years'] ?? null,
                'requires_shipping' => (bool) ($item['requires_shipping'] ?? false), 'is_active' => true,
            ]);

            foreach ($item['rules'] ?? [] as $rule) {
                $class = $targets[$rule['target_type'] ?? ''] ?? throw new BlueprintError('Unknown price target ['.($rule['target_type'] ?? '').'].');
                $target = $class::where('name', $rule['target'] ?? '')->first() ?? throw new BlueprintError('Catalog entry ['.($rule['target'] ?? '').'] does not exist. Add it with an "armorhub.catalog" step first.');

                ProductPriceRule::create(['product_id' => $product->id, 'target_type' => $rule['target_type'], 'target_id' => $target->id, 'price' => $rule['price'], 'duration_minutes' => $rule['duration_minutes'] ?? null, 'is_active' => true]);
            }

            $made[] = $product->id;
        }

        return ['product_ids' => $made];
    }
}
