<?php

namespace App\Services;

use App\Models\Product;

/**
 * What a product still needs before shoppers can trust it: pictures, a real description, stock, shipping details and so on.
 * A product is "ready" at 100; nothing is blocked, but sellers see exactly what is missing and what each item is worth to a buyer.
 */
class ProductReadiness
{
    public const MIN_PHOTOS = 3;
    public const MIN_DESCRIPTION = 80;
    public const MIN_HIGHLIGHTS = 3;

    /**
     * @return array{score: int, ready: bool, done: int, total: int, items: list<array{key: string, ok: bool, label: string, hint: string}>}
     */
    public function check(Product $product): array
    {
        $images = array_values(array_filter((array) $product->images));
        $highlights = array_values(array_filter((array) ($product->attributes['highlights'] ?? [])));
        $items = [
            ['photos', count($images) >= self::MIN_PHOTOS, 'At least 3 photos', 'Shoppers buy what they can see from every side. Add more photos.'],
            ['description', mb_strlen(trim((string) $product->description)) >= self::MIN_DESCRIPTION, 'A description of at least 80 characters', 'Say what it is, what it fits and why it is good.'],
            ['highlights', count($highlights) >= self::MIN_HIGHLIGHTS, 'At least 3 key features', 'Short bullet points near the price: material, size, what is in the box.'],
            ['brand', trim((string) $product->brand) !== '', 'The brand', 'Brand is a filter shoppers use to search.'],
            ['sku', trim((string) $product->sku) !== '', 'Your product code (SKU)', 'Lets you and the warehouse find it without doubt.'],
            ['price', (float) $product->base_price > 0, 'A price', 'A product without a price cannot be bought.'],
            ['warranty', (int) $product->warranty_years > 0, 'Warranty years', 'A stated warranty builds trust and enables warranty claims.'],
        ];

        if ($product->requires_shipping) {
            $items[] = ['stock', (int) $product->stock_cartons > 0, 'Stock on hand', 'A product with no stock cannot be ordered.'];
            $items[] = ['parcel', (float) $product->carton_weight_kg > 0 && (float) $product->carton_length_cm > 0 && (float) $product->carton_width_cm > 0 && (float) $product->carton_height_cm > 0,
                'Carton weight and size', 'Needed to price shipping and book the parcel.'];
        }

        if ($product->fitment_required) {
            $items[] = ['fitment', (isset($product->fitments_count) ? $product->fitments_count > 0 : $product->fitments()->exists()), 'The cars it fits', 'This product is only sold to cars it fits, so at least one is needed.'];
        }

        $rows = array_map(fn ($i) => ['key' => $i[0], 'ok' => $i[1], 'label' => $i[2], 'hint' => $i[3]], $items);
        $done = count(array_filter($rows, fn ($r) => $r['ok']));

        return ['score' => (int) round($done / max(1, count($rows)) * 100), 'ready' => $done === count($rows), 'done' => $done, 'total' => count($rows), 'items' => $rows];
    }
}
