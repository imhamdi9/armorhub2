<?php

namespace App\Services\Storefront;

use App\Models\Company;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * What to show next to a product: what other buyers put in the same basket, and what is like it. Only counts are used, never who bought:
 * a pairing is shown once at least MIN_BUYERS different people bought both, so it cannot point at one person's purchases.
 */
class ProductRecommendations
{
    public const MIN_BUYERS = 2;

    public function __construct(private StorefrontService $stores)
    {
    }

    /** Other products bought together with this one, most often first. @return Collection<int, Product> */
    public function alsoBought(Product $product, Company $company, int $limit = 6): Collection
    {
        $buyers = Order::withoutGlobalScopes()->where('product_id', $product->id)->whereNotNull('customer_id')->distinct()->pluck('customer_id');
        if ($buyers->count() < self::MIN_BUYERS) {
            return collect();
        }

        $counts = Order::withoutGlobalScopes()->whereIn('customer_id', $buyers)->where('product_id', '!=', $product->id)
            ->selectRaw('product_id, count(distinct customer_id) as buyers')->groupBy('product_id')->havingRaw('count(distinct customer_id) >= ?', [self::MIN_BUYERS])
            ->orderByDesc('buyers')->limit($limit * 3)->pluck('buyers', 'product_id');

        return $this->visible($company)->whereIn('id', $counts->keys())->get()->sortByDesc(fn ($p) => $counts[$p->id])->take($limit)->values();
    }

    /** Products like this one: same category, the closest price first. @return Collection<int, Product> */
    public function similar(Product $product, Company $company, int $limit = 6): Collection
    {
        $price = number_format((float) $product->base_price, 2, '.', '');

        return $this->visible($company)->where('id', '!=', $product->id)->when($product->category, fn ($q) => $q->where('category', $product->category))
            ->orderByRaw('abs('.ProductSearch::LOWEST_PRICE.' - '.$price.') asc')->orderBy('name')->limit($limit)->get();
    }

    /**
     * Picks for someone who looked at these products: others from the same categories, the ones people buy most first.
     *
     * @param  list<int>  $seen
     * @return Collection<int, Product>
     */
    public function forYou(array $seen, Company $company, int $limit = 8): Collection
    {
        if ($seen === []) {
            return collect();
        }
        $categories = $this->visible($company)->whereIn('id', $seen)->whereNotNull('category')->pluck('category')->unique();
        $popular = Order::withoutGlobalScopes()->selectRaw('product_id, count(*) as n')->groupBy('product_id')->pluck('n', 'product_id');

        return $this->visible($company)->whereNotIn('id', $seen)->whereIn('category', $categories)->limit(60)->get()
            ->sortBy([fn ($a, $b) => ($popular[$b->id] ?? 0) <=> ($popular[$a->id] ?? 0), fn ($a, $b) => strcmp($a->name, $b->name)])->take($limit)->values();
    }

    /** @return \Illuminate\Database\Eloquent\Builder<Product> */
    private function visible(Company $company)
    {
        return $this->stores->products($company);
    }
}
