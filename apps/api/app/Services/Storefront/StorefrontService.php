<?php

namespace App\Services\Storefront;

use App\Models\Company;
use App\Models\Partner;
use App\Models\Product;
use App\Models\ProductPriceRule;
use App\Modules\IndustryRegistry;
use App\Support\Settings\Settings;
use Illuminate\Database\Eloquent\Builder;

/**
 * Everything the public site may know about a company's storefront. Deliberately a short list:
 * names, descriptions and prices that are meant to be public, and nothing about people, partners'
 * contact details, orders or money.
 */
class StorefrontService
{
    private const HIDDEN_STATUSES = ['inactive', 'suspended'];

    public function __construct(private IndustryRegistry $registry, private Settings $settings, private \App\Support\Markets\Markets $markets)
    {
    }

    /** A company's storefront settings with defaults filled in. @return array<string, mixed> */
    public function settingsOf(Company $company): array
    {
        return array_merge([
            'enabled' => false,
            'headline' => null,
            'subheadline' => null,
            'about' => null,
            'primary_color' => '#111827',
            'logo_url' => null,
            'hero_image_url' => null,
            'featured_product_ids' => [],
            'contact_email' => null,
            'contact_phone' => null,
        ], $company->settings['storefront'] ?? []);
    }

    /** The company behind a public address, or null when it has no public storefront. */
    public function find(string $slug): ?Company
    {
        $company = Company::withoutGlobalScopes()->with('tenant.industry')->whereNull('deleted_at')->where('slug', $slug)->first();

        if (! $company || ! $this->settingsOf($company)['enabled'] || in_array($company->status, self::HIDDEN_STATUSES, true)) {
            return null;
        }

        return $company;
    }

    /** The country a store trades in: what the public site needs to show money and VAT correctly. */
    public function market(Company $company): ?array
    {
        $m = $this->markets->forCompany($company);

        $a = $m?->toArray();

        return $m ? ['code' => $m->code, 'name' => $a['name'], 'currency' => $m->currency(), 'locale' => $a['locale'], 'vat_rate' => $m->vatRate(),
            'prices_include_vat' => $m->pricesIncludeVat(), 'phone' => $a['phone']] : null;
    }

    /**
     * The marketplaces a business can ask to sell in: tenants that have at least one open store.
     *
     * @return list<array<string, mixed>>
     */
    public function marketplaces(): array
    {
        return Company::withoutGlobalScopes()->with('tenant.industry')->stores()->whereNotNull('slug')->whereNotIn('status', self::HIDDEN_STATUSES)->get()
            ->filter(fn (Company $c) => $this->settingsOf($c)['enabled'] && $c->tenant && $c->tenant->status === 'active' && $c->tenant->selling_enabled)
            ->unique('tenant_id')
            ->map(function (Company $c) {
                $industries = collect($this->industries($c))->pluck('name');
                $market = $this->markets->forCompany($c);

                return ['key' => $c->tenant->uuid, 'name' => $c->tenant->displayName(), 'industry' => $industries->join(', ') ?: null, 'industries' => $industries->values()->all(),
                    'market' => $market?->code, 'country' => $market?->name('en'), 'country_ar' => $market?->name('ar'), 'currency' => $market?->currency()];
            })
            ->sortBy('name')->values()->all();
    }

    /** @return Builder<Product> products a visitor may see: on sale, and in this store's industry */
    public function products(Company $company): Builder
    {
        $industries = $company->tenant?->industryIds() ?? [];
        $market = $this->markets->forCompany($company)?->code;

        return Product::query()->where('is_active', true)
            ->with('seller:id,name')
            ->withMin(['priceRules as lowest_price' => fn ($q) => $q->where('is_active', true)], 'price')
            ->withCount(['reviews as reviews_count' => fn ($q) => $q->published()])
            ->withCount('bundleItems')
            ->withAvg(['reviews as reviews_avg' => fn ($q) => $q->published()], 'rating')
            ->visibleInTenant($company->tenant_id)
            ->where(fn ($q) => $q->whereNull('market')->when($market, fn ($w) => $w->orWhere('market', $market)))
            ->where(fn ($q) => $q->whereNull('industry_id')->when($industries, fn ($w) => $w->orWhereIn('industry_id', $industries)));
    }

    /** @return array<string, mixed> */
    public function branding(Company $company): array
    {
        $s = $this->settingsOf($company);

        return [
            'name' => $company->displayName(),
            'slug' => $company->slug,
            'headline' => $s['headline'],
            'subheadline' => $s['subheadline'],
            'about' => $s['about'],
            'primary_color' => $s['primary_color'],
            'logo_url' => $s['logo_url'],
            'hero_image_url' => $s['hero_image_url'],
            'contact_email' => $s['contact_email'],
            'contact_phone' => $s['contact_phone'],
        ];
    }

    /** The industry of a single-industry market; null for a market that runs several (see industries()). @return array<string, mixed> */
    public function industry(Company $company): ?array
    {
        $all = $this->industries($company);

        return count($all) === 1 ? $all[0] : null;
    }

    /** Every industry the market runs. @return list<array<string, mixed>> */
    public function industries(Company $company): array
    {
        $tenant = $company->tenant;
        $ids = $tenant?->industryIds() ?? [];

        return \App\Models\Industry::whereIn('id', $ids)->orderBy('name')->get()
            ->map(fn ($i) => ['key' => $i->key, 'name' => $i->name, 'description' => $i->description])->values()->all();
    }

    /** @return array<string, mixed> */
    /** @return array<string, string> slug => the category's name in the current language */
    public function categoryLabels(): array
    {
        static $cache = [];
        $locale = app()->getLocale();

        return $cache[$locale] ??= \App\Models\ProductCategory::where('is_active', true)->get()->mapWithKeys(fn ($c) => [$c->key => $locale === 'ar' && $c->name_ar ? $c->name_ar : $c->name_en])->all();
    }

    /** The key features, in the language being browsed (the Arabic list when there is one, else the main one). @return list<string> */
    public function highlightsOf(Product $product): array
    {
        $main = array_values($product->attributes['highlights'] ?? []);
        $ar = array_values($product->attributes['highlights_ar'] ?? []);

        return app()->getLocale() === 'ar' && $ar !== [] ? $ar : $main;
    }

    public function card(Product $product, ?Company $company = null, ?float $from = null): array
    {
        return [
            'seller' => $product->seller_id ? $product->seller?->name : null,
            'seller_id' => $product->seller_id,
            'id' => $product->id,
            // Shoppers browsing in Arabic read the seller's Arabic copy when there is one.
            'name' => app()->getLocale() === 'ar' && $product->name_ar ? $product->name_ar : $product->name,
            'brand' => $product->brand,
            'category' => $product->category,
            'category_name' => $this->categoryLabels()[$product->category] ?? null,
            'description' => app()->getLocale() === 'ar' && $product->description_ar ? $product->description_ar : $product->description,
            'warranty_years' => $product->warranty_years,
            'images' => array_values($product->images ?? []),
            'compare_at_price' => $product->compare_at_price !== null ? round((float) $product->compare_at_price, 2) : null,
            'requires_shipping' => (bool) $product->requires_shipping,
            'installation' => $product->installation ?? 'none',
            'fulfilled_by_armorhub' => $product->fulfilment === 'armorhub',
            'in_stock' => $product->fulfilment !== 'armorhub' || app(\App\Services\Warehouse\WarehouseService::class)->availableOf($product) > 0,
            // "Only N left": shown when ArmorHub holds the stock and it is running low.
            'units_left' => $product->fulfilment === 'armorhub' ? (($u = app(\App\Services\Warehouse\WarehouseService::class)->availableOf($product)) > 0 && $u <= 5 ? $u : null) : null,
            'from_price' => $from ?? $this->fromPrice($product),
            'currency' => $this->markets->currencyFor($company),
            'rating' => $this->rating($product),
            'sale_mode' => $product->sale_mode ?? 'fixed',
            // A bundle the seller made: shown with a bundle mark everywhere it appears.
            'is_bundle' => (int) ($product->bundle_items_count ?? $product->bundleItems()->count()) > 0,
        ];
    }

    /** The stars shown on a product card: the average of the published reviews and how many there are (null average while there are none). */
    private function rating(Product $product): array
    {
        // Already counted by the query that loaded the product (see products()).
        if (array_key_exists('reviews_count', $product->getAttributes())) {
            $count = (int) $product->reviews_count;

            return ['count' => $count, 'average' => $count > 0 ? round((float) $product->reviews_avg, 1) : null];
        }
        $row = \App\Models\ProductReview::where('product_id', $product->id)->published()->selectRaw('count(*) as c, avg(rating) as a')->first();
        $count = (int) ($row?->c ?? 0);

        return ['count' => $count, 'average' => $count > 0 ? round((float) $row->a, 1) : null];
    }

    /** The lowest public price: the cheapest rule, or the base price when no rules exist. */
    public function fromPrice(Product $product): float
    {
        if (array_key_exists('lowest_price', $product->getAttributes())) {
            return round((float) ($product->lowest_price ?? $product->base_price), 2);
        }
        $min = ProductPriceRule::where('product_id', $product->id)->where('is_active', true)->min('price');

        return round((float) ($min ?? $product->base_price), 2);
    }

    /** What each option costs for this product, e.g. "Hood: 1200". @return list<array<string, mixed>> */
    public function priceList(Product $product): array
    {
        $rules = ProductPriceRule::where('product_id', $product->id)->where('is_active', true)->orderBy('price')->get();
        $classes = $this->registry->targetModels();
        $names = [];

        foreach ($rules->groupBy('target_type') as $type => $group) {
            if (isset($classes[$type])) {
                $names[$type] = $classes[$type]::whereIn('id', $group->pluck('target_id'))->pluck('name', 'id');
            }
        }

        return $rules->map(fn ($r) => [
            'target_type' => $r->target_type,
            'target_id' => (int) $r->target_id,
            // Catalogue names (a car part, a room, a device part) are shown in the shopper's language.
            'target' => isset($names[$r->target_type][$r->target_id]) ? __($names[$r->target_type][$r->target_id]) : null,
            'price' => round((float) $r->price, 2),
            'duration_minutes' => $r->duration_minutes,
        ])->filter(fn ($r) => $r['target'] !== null)->values()->all();
    }

    /**
     * The home page showcase: every open store grouped by industry, each with the partners that chose to be
     * shown and the products on offer. Partners expose only name, city, description and logo.
     *
     * @return list<array<string, mixed>>
     */
    public function showcase(): array
    {
        $stores = Company::withoutGlobalScopes()->with('tenant.industry')->stores()->whereNotNull('slug')
            ->whereNotIn('status', self::HIDDEN_STATUSES)->orderBy('name')->get()
            ->filter(fn (Company $c) => $this->settingsOf($c)['enabled'] && $c->tenant?->industry)
            ->groupBy(fn (Company $c) => $c->tenant->industry->key);

        return $stores->map(function ($companies) {
            $industry = $companies->first()->tenant->industry;

            return [
                'industry' => ['key' => $industry->key, 'name' => $industry->name, 'description' => $industry->description],
                'stores' => $companies->map(fn (Company $c) => [
                    'name' => $c->displayName(),
                    'slug' => $c->slug,
                    'market' => $this->market($c)['code'] ?? null,
                    'primary_color' => $this->settingsOf($c)['primary_color'],
                    'partners' => Partner::withoutGlobalScopes()->where('is_active', true)->where('public_profile', true)
                        ->whereHas('company', fn ($q) => $q->withoutGlobalScopes()->where('tenant_id', $c->tenant_id))
                        ->orderBy('name')->limit(12)->get()
                        ->map(fn (Partner $p) => ['name' => $p->name, 'city' => $p->city, 'country' => $p->country, 'description' => $p->description, 'logo_url' => $p->logo_url])
                        ->values(),
                    'products' => $this->products($c)->orderBy('name')->limit(6)->get()->map(fn ($p) => $this->card($p, $c))->values(),
                ])->values(),
            ];
        })->values()->all();
    }

    /** Where this store's partners are: a count and city names, never contact details. */
    public function partnerSummary(Company $company): array
    {
        $partners = Partner::withoutGlobalScopes()->where('is_active', true)
            ->whereHas('company', fn ($q) => $q->withoutGlobalScopes()->where('tenant_id', $company->tenant_id));

        return [
            'count' => (clone $partners)->count(),
            'cities' => (clone $partners)->whereNotNull('city')->distinct()->orderBy('city')->limit(12)->pluck('city'),
        ];
    }
}
