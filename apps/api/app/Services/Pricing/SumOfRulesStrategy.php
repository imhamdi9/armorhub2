<?php

namespace App\Services\Pricing;

use App\Models\Product;
use App\Modules\Contracts\PricingStrategy;
use Illuminate\Support\Collection;

/** Default: the price is the sum of the matching rules. */
class SumOfRulesStrategy implements PricingStrategy
{
    public function price(Product $product, Collection $rules): array
    {
        return [
            'total' => $rules->sum('price'),
            'parts_count' => $rules->count(),
        ];
    }
}
