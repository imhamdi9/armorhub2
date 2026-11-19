<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Storefront\StorefrontService;

/**
 * The product feed of one market for the ad platforms: Google Merchant Center (XML) and Meta or TikTok catalogues (CSV).
 * Only what can be bought straight from an ad is listed: a price that is fixed, no quote, no installation offer, in stock, with a picture.
 */
class ProductFeedController extends Controller
{
    public function __construct(private StorefrontService $stores)
    {
    }

    /** @return list<array<string, string>> */
    public function items(string $slug): array
    {
        $company = $this->stores->find($slug) ?? abort(404);
        $web = rtrim((string) config('platform.web_url'), '/');
        $items = [];

        foreach ($this->stores->products($company)->limit(5000)->get() as $product) {
            $card = $this->stores->card($product, $company);
            $image = collect($card['images'])->first();
            $excluded = ($card['sale_mode'] ?? 'fixed') !== 'fixed' || ($card['installation'] ?? 'none') === 'required' || ! $card['in_stock']
                || ! $card['from_price'] || ! $image || ! $card['requires_shipping'];
            if ($excluded) {
                continue;
            }
            $image = str_starts_with((string) $image, '/media/') ? url('/api/public'.$image) : (string) $image;
            $description = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($card['description'] ?? ''))) ?? '');

            $items[] = [
                'id' => (string) $card['id'],
                'title' => mb_substr((string) $card['name'], 0, 150),
                'description' => mb_substr($description !== '' ? $description : (string) $card['name'], 0, 4900),
                'link' => "{$web}/s/{$company->slug}/products/{$card['id']}",
                'image_link' => $image,
                'availability' => 'in stock',
                'price' => number_format((float) ($card['compare_at_price'] && $card['compare_at_price'] > $card['from_price'] ? $card['compare_at_price'] : $card['from_price']), 2, '.', '').' '.$card['currency'],
                'sale_price' => $card['compare_at_price'] && $card['compare_at_price'] > $card['from_price'] ? number_format((float) $card['from_price'], 2, '.', '').' '.$card['currency'] : '',
                'brand' => (string) ($card['brand'] ?: ($card['seller'] ?? '')),
                'condition' => 'new',
                'product_type' => (string) ($card['category_name'] ?? $card['category'] ?? ''),
                'price_number' => (string) ($card['from_price']),
                'currency' => (string) $card['currency'],
                // Most sellers have no barcode: say so, otherwise Merchant Center holds the product back.
                'identifier_exists' => 'no',
            ];
        }

        return $items;
    }

    /** Delivery as the market sets it, for the shipping column Merchant Center and the catalogues ask for. */
    private function shipping(string $slug, array $item): string
    {
        $company = $this->stores->find($slug);
        $code = $company ? $this->stores->market($company)['code'] ?? null : null;
        if (! $code) {
            return '';
        }
        $settings = app(\App\Support\Settings\Settings::class);
        $fee = $settings->inMarket($company->tenant_id, fn () => [$settings->get("shipping.flat_fee.{$code}"), $settings->get("shipping.free_over.{$code}")]);
        if ($fee[0] === null || $fee[0] === '') {
            return '';
        }
        $price = (float) $item['price_number'];
        $amount = $fee[1] !== null && $fee[1] !== '' && $price >= (float) $fee[1] ? 0.0 : (float) $fee[0];

        return $code.'::Standard:'.number_format($amount, 2, '.', '').' '.$item['currency'];
    }

    public function show(string $slug, string $file)
    {
        // "-ar" asks for the Arabic names and descriptions (the same product, in the shopper's language).
        if (str_contains($file, '-ar.')) {
            app()->setLocale('ar');
            $file = str_replace('-ar.', '.', $file);
        }
        $items = $this->items($slug);

        if ($file === 'google.xml') {
            $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0"><channel><title>'.e($slug).'</title><link>'.e(rtrim((string) config('platform.web_url'), '/')."/s/{$slug}").'</link><description>Products</description>';
            foreach ($items as $i) {
                $xml .= '<item>'.collect([
                    'g:id' => $i['id'], 'title' => $i['title'], 'description' => $i['description'], 'link' => $i['link'], 'g:image_link' => $i['image_link'],
                    'g:availability' => 'in_stock', 'g:price' => $i['price'], 'g:sale_price' => $i['sale_price'], 'g:brand' => $i['brand'], 'g:condition' => 'new', 'g:product_type' => $i['product_type'], 'g:identifier_exists' => 'no',
                ])->filter(fn ($v) => $v !== '')->map(fn ($v, $k) => "<{$k}>".htmlspecialchars((string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8')."</{$k}>")->implode('');
                if ($ship = $this->shipping($slug, $i)) {
                    [$country, , $service, $price] = array_pad(explode(':', $ship), 4, '');
                    $xml .= '<g:shipping><g:country>'.e($country).'</g:country><g:service>'.e($service).'</g:service><g:price>'.e($price).'</g:price></g:shipping>';
                }
                $xml .= '</item>';
            }

            return response($xml.'</channel></rss>', 200, ['Content-Type' => 'application/xml; charset=utf-8', 'Cache-Control' => 'public, max-age=900']);
        }

        // Meta and TikTok read the same columns.
        $columns = ['id', 'title', 'description', 'availability', 'condition', 'price', 'sale_price', 'link', 'image_link', 'brand', 'product_type', 'shipping'];
        $out = fopen('php://temp', 'r+');
        fputcsv($out, $columns);
        foreach ($items as $i) {
            $i['shipping'] = $this->shipping($slug, $i);
            fputcsv($out, array_map(fn ($c) => $i[$c], $columns));
        }
        rewind($out);

        return response(stream_get_contents($out), 200, ['Content-Type' => 'text/csv; charset=utf-8', 'Cache-Control' => 'public, max-age=900']);
    }
}
