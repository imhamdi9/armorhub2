<?php

namespace App\Services\Pricing;

use App\Models\Product;
use App\Models\ProductPriceRule;
use App\Modules\IndustryRegistry;

class CalculateProductPriceService
{
    public function __construct(
        private IndustryRegistry $industries,
        private SumOfRulesStrategy $default,
    ) {
    }

    /**
     * @param  array<int>  $targetIds   ids of the selected targets (vehicle parts, rooms, ...)
     * @param  string|null $targetType  morph alias of the targets; null prices rules of any type
     */
    public function calculate(
        int $productId,
        array $targetIds,
        ?string $targetType = null
    ): array {

        $product = Product::with('industry')->findOrFail($productId);

        $rules = ProductPriceRule::query()
            ->where('product_id', $product->id)
            ->when($targetType, fn ($q) => $q->where('target_type', $targetType))
            ->whereIn('target_id', $targetIds)
            ->where('is_active', true)
            ->get();

        $module = $product->industry?->module();
        $strategy = $module?->pricingStrategy() ?? $this->default;

        $result = $strategy->price($product, $rules);

        return [
            'product_id' => $product->id,
            'parts_count' => $result['parts_count'],
            'total' => $result['total'],
            'currency' => 'USD',
        ];
    }
}
