<?php

namespace App\Services;

use App\Models\Product;
use App\Support\Markets\Markets;
use Illuminate\Support\Facades\DB;

/** Tells the people who saved a product that its price went down. Raises, and changes that leave the price where it was, tell no one. */
class PriceAlerts
{
    public function __construct(private InstallationNotifier $notify, private Markets $markets)
    {
    }

    public function dropped(Product $product, ?float $old, ?float $new): void
    {
        if ($old === null || $new === null || $new >= $old - 0.004) {
            return;
        }
        $currency = $product->market ? ($this->markets->get($product->market)?->currency() ?? '') : '';
        $now = number_format($new, 2).($currency !== '' ? " {$currency}" : '');

        foreach (DB::table('favourites')->where('product_id', $product->id)->pluck('user_id') as $userId) {
            $this->notify->user((int) $userId, 'Price drop', ':name is now :price.', '/dashboard/favourites', ['name' => (string) $product->name, 'price' => (string) $now]);
        }
    }
}
