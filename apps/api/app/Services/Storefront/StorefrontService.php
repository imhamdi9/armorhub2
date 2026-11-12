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
        $company = Company::withoutGlobalScopes()->with('tenant.industry')->where('slug', $slug)->first();

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
        return Company::withoutGlobalScopes()->with('tenant.industry')->whereNotNull('slug')->whereNotIn('status', self::HIDDEN_STATUSES)->get()
            ->filter(fn (Company $c) => $this->settingsOf($c)['enabled'] && $c->tenant && $c->tenant->status === 'active')
            ->unique('tenant_id')
            ->map(fn (Company $c) => ['key' => $c->tenant->uuid, 'name' => $c->tenant->name, 'industry' => $c->tenant->industry?->name,
                'market' => $this->market($c)['code'] ?? null])
            ->sortBy('name')->values()->all();
    }

    /** @return Builder<Product> products a visitor may see: on sale, and in this store's industry */
    public function products(Company $company): Builder
    {
        $industry = $company->tenant?->industry_id;
        $market = $this->markets->forCompany($company)?->code;

        return Product::query()->where('is_active', true)
            ->visibleInTenant($company->tenant_id)
            ->where(fn ($q) => $q->whereNull('market')->when($market, fn ($w) => $w->orWhere('market', $market)))
            ->where(fn ($q) => $q->whereNull('industry_id')->when($industry, fn ($w) => $w->orWhere('industry_id', $industry)));
    }

    /** @return array<string, mixed> */
    public function branding(Company $company): array
    {
        $s = $this->settingsOf($company);

        return [
            'name' => $company->name,
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

    /** @return array<string, mixed> */
    public function industry(Company $company): ?array
    {
        $industry = $company->tenant?->industry;

        return $industry ? ['key' => $industry->key, 'name' => $industry->name, 'description' => $industry->description] : null;
    }

    /** @return array<string, mixed> */
    public function card(Product $product, ?Company $company = null, ?float $from = null): array
    {
        return [
            'seller' => $product->seller_id ? $product->seller?->name : null,
            'id' => $product->id,
            'name' => $product->name,
            'brand' => $product->brand,
            'category' => $product->category,
            'description' => $product->description,
            'warranty_years' => $product->warranty_years,
            'images' => array_values($product->images ?? []),
            'compare_at_price' => $product->compare_at_price !== null ? round((float) $product->compare_at_price, 2) : null,
            'requires_shipping' => (bool) $product->requires_shipping,
            'from_price' => $from ?? $this->fromPrice($product),
            'currency' => $this->markets->currencyFor($company),
        ];
    }

    /** The lowest public price: the cheapest rule, or the base price when no rules exist. */
    public function fromPrice(Product $product): float
    {
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
            'target' => $names[$r->target_type][$r->target_id] ?? null,
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
        $stores = Company::withoutGlobalScopes()->with('tenant.industry')->whereNotNull('slug')
            ->whereNotIn('status', self::HIDDEN_STATUSES)->orderBy('name')->get()
            ->filter(fn (Company $c) => $this->settingsOf($c)['enabled'] && $c->tenant?->industry)
            ->groupBy(fn (Company $c) => $c->tenant->industry->key);

        return $stores->map(function ($companies) {
            $industry = $companies->first()->tenant->industry;

            return [
                'industry' => ['key' => $industry->key, 'name' => $industry->name, 'description' => $industry->description],
                'stores' => $companies->map(fn (Company $c) => [
                    'name' => $c->name,
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
