<?php

namespace App\Services;

use App\Models\Industry;
use App\Models\Product;
use App\Models\ProductPriceRule;

/**
 * Goods are bought for one of the industry's "options" (for a car: the whole car), so a product with only a base price cannot be added to a cart.
 * A seller should not have to know that: the base price becomes the price of the whole thing, and follows the base price while the seller has not
 * set richer prices of their own.
 */
class DefaultPrice
{
    /** Gives a seller's product its buyable price when it has none. */
    public function ensure(Product $product): void
    {
        if (! $product->seller_id || ProductPriceRule::where('product_id', $product->id)->exists()) {
            return;
        }
        $module = $product->industry_id ? Industry::find($product->industry_id)?->module() : null;
        $models = $module?->targetModels() ?? [];
        $type = array_key_first($models);
        if (! $type) {
            return;
        }
        $class = $models[$type];
        $target = $class::where('slug', 'like', 'whole%')->orWhere('slug', 'like', 'full%')->orderBy('id')->first() ?? $class::orderBy('id')->first();
        if ($target) {
            ProductPriceRule::create(['product_id' => $product->id, 'target_type' => $type, 'target_id' => $target->id, 'price' => $product->base_price, 'duration_minutes' => 0, 'is_active' => true]);
        }
    }

    /** The base price changed: a lone price that still equals the old base price follows it. */
    public function follow(Product $product, float $oldPrice): void
    {
        $rules = ProductPriceRule::where('product_id', $product->id)->get();
        if ($rules->count() === 1 && abs((float) $rules->first()->price - $oldPrice) < 0.005) {
            $rules->first()->update(['price' => $product->base_price]);
        }
    }
}
