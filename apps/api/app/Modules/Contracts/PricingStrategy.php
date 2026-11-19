<?php

namespace App\Modules\Contracts;

use App\Models\Product;
use Illuminate\Support\Collection;

interface PricingStrategy
{
    /**
     * @param  Collection<int, \App\Models\ProductPriceRule>  $rules  active rules matching the selected targets
     * @return array{total: float|int|string, parts_count: int}
     */
    public function price(Product $product, Collection $rules): array;
}
