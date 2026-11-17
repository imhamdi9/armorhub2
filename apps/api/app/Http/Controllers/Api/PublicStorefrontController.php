<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\Storefront\StorefrontService;
use Illuminate\Http\Request;

/**
 * The public, unauthenticated side of the platform: browse a store and its catalog without an account.
 * Read-only, rate-limited, cacheable, and limited to information meant to be public.
 */
class PublicStorefrontController extends Controller
{
    public function __construct(private StorefrontService $stores)
    {
    }

    /** All open storefronts (for the platform's home page). */
    public function index()
    {
        $stores = Company::withoutGlobalScopes()->with('tenant.industry')
            ->stores()->whereNotNull('slug')->whereNotIn('status', ['inactive', 'suspended'])
            ->get()
            ->filter(fn (Company $c) => $this->stores->settingsOf($c)['enabled'])
            ->map(fn (Company $c) => [
                'name' => $c->name,
                'slug' => $c->slug,
                'headline' => $this->stores->settingsOf($c)['headline'],
                'primary_color' => $this->stores->settingsOf($c)['primary_color'],
                'logo_url' => $this->stores->settingsOf($c)['logo_url'],
                'industry' => $this->stores->industry($c),
                'market' => $this->stores->market($c)['code'] ?? null,
            ])->values();

        return $this->cached(['stores' => $stores]);
    }

    /** Marketplaces open to new sellers. */
    public function marketplaces()
    {
        return $this->cached(['marketplaces' => $this->stores->marketplaces()]);
    }

    /** The countries the platform operates in, with their currency, VAT and phone rules. */
    public function markets(\App\Support\Markets\Markets $markets)
    {
        return $this->cached(['markets' => collect($markets->all())->map->toArray()->values()]);
    }

    /** Home page: partners and products, one block per industry. */
    /** Where a market is open: shown so a visitor knows whether they are covered. */
    public function zones(\Illuminate\Http\Request $request)
    {
        $request->validate(['market' => 'nullable|string|size:2']);

        return $this->cached(['zones' => \App\Models\ServiceZone::where('is_active', true)->when($request->query('market'), fn ($q, $m) => $q->where('market', strtoupper((string) $m)))->orderBy('position')
            ->get(['market', 'key', 'name_en', 'name_ar', 'city'])]);
    }

    public function showcase()
    {
        return $this->cached(['industries' => $this->stores->showcase()]);
    }

    public function show(string $slug)
    {
        // An older per-industry store address forwards to the country's market store.
        $old = \App\Models\Company::withoutGlobalScopes()->where('slug', $slug)->first();
        if ($old && ($to = ($old->settings['merged_into'] ?? null))) {
            return response()->json(['merged_into' => $to]);
        }

        $company = $this->stores->find($slug) ?? abort(404);
        $settings = $this->stores->settingsOf($company);

        $featured = collect($settings['featured_product_ids'])->isNotEmpty()
            ? $this->stores->products($company)->whereIn('id', $settings['featured_product_ids'])->get()
            : $this->stores->products($company)->orderBy('id')->limit(4)->get();

        // The catalogue, organised: one block per category (biggest first, then A to Z) with a few of its products.
        $all = $this->stores->products($company)->whereNotNull('category')->orderBy('name')->get();
        $sections = $all->groupBy('category')
            ->map(fn ($items, $cat) => ['category' => $cat, 'count' => $items->count(), 'products' => $items->take(4)->map(fn ($p) => $this->stores->card($p, $company))->values()])
            ->sortBy([['count', 'desc'], ['category', 'asc']])->values();

        return $this->cached([
            'store' => $this->stores->branding($company),
            'sections' => $sections,
            'category_counts' => $all->countBy('category')->sortKeys(),
            'industry_counts' => $all->groupBy('industry_id')->map->count()->mapWithKeys(fn ($n, $id) => [\App\Models\Industry::where('id', $id)->value('key') => $n]),
            'industry' => $this->stores->industry($company),
            'industries' => $this->stores->industries($company),
            'market' => $this->stores->market($company),
            'featured' => $featured->map(fn ($p) => $this->stores->card($p, $company))->values(),
            'categories' => $this->stores->products($company)->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'partners' => $this->stores->partnerSummary($company),
        ]);
    }

    public function products(Request $request, string $slug)
    {
        $request->validate(['q' => 'nullable|string|max:80', 'category' => 'nullable|string|max:80', 'industry' => 'nullable|string|max:60', 'page' => 'nullable|integer|min:1|max:1000', 'per_page' => 'nullable|integer|min:1|max:50', 'sort' => 'nullable|in:category,price_asc,price_desc,name']);
        $company = $this->stores->find($slug) ?? abort(404);

        // The lowest public price: the cheapest active price rule, or the base price when there are none.
        $lowest = '(select coalesce(min(r.price), products.base_price) from product_price_rules r where r.product_id = products.id and r.is_active = 1)';

        $page = $this->stores->products($company)
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', '%'.addcslashes($request->q, '%_\\').'%')
                ->orWhere('brand', 'like', '%'.addcslashes($request->q, '%_\\').'%')))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->category))
            ->when($request->filled('industry'), fn ($q) => $q->whereIn('industry_id', \App\Models\Industry::where('key', $request->industry)->select('id')))
            // Grouped by category, then A to Z, so the list reads as sections rather than a shuffle; or by price or name when asked.
            ->when($request->input('sort') === 'price_asc', fn ($q) => $q->orderByRaw("$lowest asc")->orderBy('name'))
            ->when($request->input('sort') === 'price_desc', fn ($q) => $q->orderByRaw("$lowest desc")->orderBy('name'))
            ->when($request->input('sort') === 'name', fn ($q) => $q->orderBy('name'))
            ->when(! in_array($request->input('sort'), ['price_asc', 'price_desc', 'name'], true), fn ($q) => $q->orderBy('category')->orderBy('name'))
            // "Only what fits my car" (and what fits everything), for a make/model and year.
            ->when($request->filled('vehicle_model_id') && $request->filled('year') && $request->boolean('compatible'), fn ($q) => app(\App\Services\Fitment\FitmentService::class)->scopeFitting($q, (int) $request->input('vehicle_model_id'), (int) $request->input('year')))
            ->paginate((int) $request->input('per_page', 12))
            ->through(fn ($p) => $this->stores->card($p, $company) + $this->fits($p, $request));

        return $this->cached($page);
    }

    /** A seller's page inside the market ("Sold by ..."): who they are and what they sell here. A seller has no store of its own. */
    public function seller(Request $request, string $slug, int $id)
    {
        $request->validate(['page' => 'nullable|integer|min:1|max:1000']);
        $company = $this->stores->find($slug) ?? abort(404);
        $seller = \App\Models\Company::withoutGlobalScopes()->where('tenant_id', $company->tenant_id)->where('type', 'supplier')->where('status', 'active')->findOrFail($id);

        $page = $this->stores->products($company)->where('seller_id', $seller->id)->orderBy('category')->orderBy('name')->paginate(12)
            ->through(fn ($p) => $this->stores->card($p, $company));

        return $this->cached(['seller' => ['id' => $seller->id, 'name' => $seller->name, 'city' => $seller->city ?? null, 'since' => $seller->created_at?->format('Y-m')], 'store' => ['name' => $company->name, 'slug' => $company->slug], 'products' => $page]);
    }

    /** The content the page layout asks for, with only the known keys (older products have none). */
    private function pageContent(\App\Models\Product $product): array
    {
        $page = is_array($product->attributes['page'] ?? null) ? $product->attributes['page'] : [];

        return array_intersect_key($page, array_flip(['headline', 'steps', 'faqs', 'specs', 'tiers', 'free_delivery_from', 'warranty_terms', 'lead_time', 'quote_note']));
    }

    /** The products the seller chose to offer next to this one, only if they are really for sale in this store. */
    private function addons(\App\Models\Product $product, $company): array
    {
        $ids = array_map('intval', (array) ($product->attributes['page']['addon_ids'] ?? []));
        if ($ids === []) {
            return [];
        }

        return $this->stores->products($company)->whereIn('id', $ids)->where('id', '!=', $product->id)->limit(4)->get()->map(fn ($p) => $this->stores->card($p, $company))->all();
    }

    /** Whether the product fits the car named in the request (?vehicle_model_id=&year=), when one is named. @return array<string, string> */
    private function fits(\App\Models\Product $product, \Illuminate\Http\Request $request): array
    {
        if (! $request->filled('vehicle_model_id') || ! $request->filled('year')) {
            return [];
        }

        return ['fits' => app(\App\Services\Fitment\FitmentService::class)->check($product, (int) $request->input('vehicle_model_id'), (int) $request->input('year'))];
    }

    public function product(\Illuminate\Http\Request $request, string $slug, int $id)
    {
        $company = $this->stores->find($slug) ?? abort(404);
        $product = $this->stores->products($company)->findOrFail($id);

        // More from the same store: the same category first, then anything else, never this product.
        $related = $this->stores->products($company)->where('id', '!=', $product->id)
            ->orderByRaw('category = ? desc', [$product->category])->orderBy('name')->limit(8)->get()
            ->map(fn ($p) => $this->stores->card($p, $company))->all();

        return $this->cached([
            'product' => $this->stores->card($product, $company) + ['prices' => $this->stores->priceList($product), 'highlights' => array_values($product->attributes['highlights'] ?? []), 'fitment_required' => (bool) $product->fitment_required, 'fitments' => app(\App\Services\Fitment\FitmentService::class)->describe($product) + [], 'page' => $this->pageContent($product), 'addons' => $this->addons($product, $company), 'page_template' => in_array($product->page_template, \App\Models\Product::PAGE_TEMPLATES, true) ? $product->page_template : 'classic'],
            'related' => $related,
            'reviews' => $this->reviews($product->id),
            'customer_fee_percentage' => (float) app(\App\Support\Settings\Settings::class)->get('platform.customer_fee_percentage'),
            'store' => ['name' => $company->name, 'slug' => $company->slug, 'contact_phone' => $this->stores->settingsOf($company)['contact_phone'] ?? null],
        ]);
    }

    /**
     * What a partner or seller needs to know before joining, from the live settings and counts: the commission and exactly how it is shared,
     * how many requests are open now and how many verified partners are active. Counts are totals only, never who asked.
     */
    public function terms()
    {
        $settings = app(\App\Support\Settings\Settings::class);
        $split = [
            'platform' => (float) $settings->get('platform.commission_split.platform'),
            'company' => (float) $settings->get('platform.commission_split.company'),
            'partner' => (float) $settings->get('platform.commission_split.partner'),
        ];

        return response()->json([
            'commission_percentage' => (float) $settings->get('platform.commission_percentage'),
            'customer_fee_percentage' => (float) $settings->get('platform.customer_fee_percentage'),
            'plans' => \App\Models\SubscriptionPlan::where('is_active', true)->orderBy('sort')->get(['key', 'name', 'price_monthly', 'currency', 'customer_limit', 'commission_percentage', 'team_seats']),
            'split' => $split,
            'open_requests' => \App\Models\Order::withoutGlobalScopes()->whereIn('status', [\App\Enums\OrderStatus::PendingQuotes->value, \App\Enums\OrderStatus::QuotesReceived->value])->count(),
            'verified_partners' => \App\Models\Partner::withoutGlobalScopes()->where('is_active', true)->count(),
        ])->header('Cache-Control', 'public, max-age=60, s-maxage=60');
    }

    /** The average rating and the latest published reviews. Only a first name and the date are shown, never an email. */
    private function reviews(int $productId): array
    {
        $published = \App\Models\ProductReview::where('product_id', $productId)->published();
        $count = (clone $published)->count();

        return [
            'count' => $count,
            'average' => $count > 0 ? round((float) (clone $published)->avg('rating'), 1) : null,
            'items' => (clone $published)->with('user:id,name')->latest()->limit(6)->get()->map(fn ($r) => [
                'id' => $r->id,
                'rating' => $r->rating,
                'comment' => $r->comment,
                'author' => explode(' ', trim((string) $r->user?->name))[0] ?: 'Customer',
                'date' => $r->created_at?->toDateString(),
            ])->all(),
        ];
    }

    /** Public data may be cached by browsers and CDNs for a minute. */
    private function cached($data)
    {
        return response()->json($data)->header('Cache-Control', 'public, max-age=60, s-maxage=60');
    }
}
