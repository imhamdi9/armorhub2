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
                'selling_enabled' => (bool) $c->tenant?->selling_enabled,
                'installation_enabled' => (bool) $c->tenant?->installation_enabled,
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
            // Each category's name in the language the shopper is browsing in.
            'category_labels' => $this->stores->categoryLabels(),
            'partners' => $this->stores->partnerSummary($company),
        ]);
    }

    public function products(Request $request, string $slug)
    {
        $request->validate(['q' => 'nullable|string|max:80', 'category' => 'nullable|string|max:80', 'industry' => 'nullable|string|max:60', 'page' => 'nullable|integer|min:1|max:1000', 'per_page' => 'nullable|integer|min:1|max:50',
            'sort' => 'nullable|in:category,price_asc,price_desc,name,rating', 'min_price' => 'nullable|numeric|min:0', 'max_price' => 'nullable|numeric|min:0', 'brand' => 'nullable|string|max:200', 'min_rating' => 'nullable|integer|min:1|max:5']);
        $company = $this->stores->find($slug) ?? abort(404);
        $search = app(\App\Services\Storefront\ProductSearch::class);
        $lowest = \App\Services\Storefront\ProductSearch::LOWEST_PRICE;
        $rating = \App\Services\Storefront\ProductSearch::AVERAGE_RATING;

        // What the shop sells that matches the words typed: every word must appear in the name, the brand or the category.
        $matching = fn ($q, ?string $text) => $q->when(filled($text), function ($q) use ($text, $search) {
            $words = $search->tokens((string) $text);
            if ($words === []) {
                $q->whereRaw('1 = 0');   // only symbols were typed: nothing can match them
            }
            foreach ($words as $word) {
                $like = '%'.addcslashes($word, '%_\\').'%';
                $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('brand', 'like', $like)->orWhere('category', 'like', $like));
            }
        });
        $scoped = fn (?string $text) => $this->stores->products($company)
            ->tap(fn ($q) => $matching($q, $text))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->category))
            ->when($request->filled('industry'), fn ($q) => $q->whereIn('industry_id', \App\Models\Industry::where('key', $request->industry)->select('id')));
        $page = function (?string $text) use ($request, $scoped, $lowest, $rating) {
            return $scoped($text)
                ->when($request->filled('min_price'), fn ($q) => $q->whereRaw("$lowest >= ".$this->number($request->min_price)))
                ->when($request->filled('max_price'), fn ($q) => $q->whereRaw("$lowest <= ".$this->number($request->max_price)))
                ->when($request->filled('brand'), fn ($q) => $q->whereIn('brand', array_filter(array_map('trim', explode(',', (string) $request->brand)))))
                ->when($request->filled('min_rating'), fn ($q) => $q->whereRaw("$rating >= ".$this->number($request->min_rating)))
                // Grouped by category, then A to Z, so the list reads as sections rather than a shuffle; or by price, rating or name when asked.
                ->when($request->input('sort') === 'price_asc', fn ($q) => $q->orderByRaw("$lowest asc")->orderBy('name'))
                ->when($request->input('sort') === 'price_desc', fn ($q) => $q->orderByRaw("$lowest desc")->orderBy('name'))
                ->when($request->input('sort') === 'rating', fn ($q) => $q->orderByRaw("coalesce($rating, 0) desc")->orderBy('name'))
                ->when($request->input('sort') === 'name', fn ($q) => $q->orderBy('name'))
                ->when(! in_array($request->input('sort'), ['price_asc', 'price_desc', 'rating', 'name'], true), fn ($q) => $q->orderBy('category')->orderBy('name'))
                // "Only what fits my car" (and what fits everything), for a make/model and year.
                ->when($request->filled('vehicle_model_id') && $request->filled('year') && $request->boolean('compatible'), fn ($q) => app(\App\Services\Fitment\FitmentService::class)->scopeFitting($q, (int) $request->input('vehicle_model_id'), (int) $request->input('year')))
                ->paginate((int) $request->input('per_page', 12));
        };

        $typed = trim((string) $request->input('q', ''));
        $results = $page($typed);
        $corrected = null;
        // Nothing matched: read the words as the closest ones the shop really uses, and say so.
        if ($typed !== '' && $results->total() === 0 && ($fixed = $search->correct($typed, $search->vocabulary($this->stores->products($company))))) {
            $corrected = $fixed;
            $results = $page($fixed);
        }

        $out = $results->through(fn ($p) => $this->stores->card($p, $company) + $this->fits($p, $request))->toArray();
        $out['search'] = ['q' => $typed, 'corrected' => $corrected];
        $out['facets'] = $search->facets($scoped($corrected ?? $typed));

        return $this->cached($out);
    }

    /**
     * Products by id, in the order asked: what someone viewed lately, or wants to compare. With detail=1 each also carries what a comparison needs
     * (features and specifications). Ids that are not for sale here are left out.
     */
    public function byIds(Request $request, string $slug)
    {
        $request->validate(['ids' => 'required|string|max:200', 'detail' => 'nullable|boolean']);
        $company = $this->stores->find($slug) ?? abort(404);
        $ids = collect(explode(',', (string) $request->input('ids')))->map(fn ($i) => (int) $i)->filter()->unique()->take($request->boolean('detail') ? 4 : 12)->values();
        $found = $this->stores->products($company)->whereIn('id', $ids)->get()->keyBy('id');

        $rows = $ids->filter(fn ($id) => $found->has($id))->map(function ($id) use ($found, $company, $request) {
            $p = $found[$id];
            $card = $this->stores->card($p, $company);

            return $request->boolean('detail') ? $card + ['highlights' => array_values($p->attributes['highlights'] ?? []), 'specs' => $this->pageContent($p)['specs'] ?? [], 'seller_name' => $card['seller']] : $card;
        })->values();

        return $this->cached(['products' => $rows]);
    }

    /** Picks for someone who looked at the products in ?ids=. */
    public function recommendations(Request $request, string $slug)
    {
        $request->validate(['ids' => 'nullable|string|max:200']);
        $company = $this->stores->find($slug) ?? abort(404);
        $ids = collect(explode(',', (string) $request->input('ids')))->map(fn ($i) => (int) $i)->filter()->unique()->take(12)->values()->all();

        return $this->cached(['products' => app(\App\Services\Storefront\ProductRecommendations::class)->forYou($ids, $company)->map(fn ($p) => $this->stores->card($p, $company))->all()]);
    }

    /** What to type next: names, brands and categories that match the first letters (and the closest words for a typo). */
    public function suggest(Request $request, string $slug)
    {
        $request->validate(['q' => 'nullable|string|max:80']);
        $company = $this->stores->find($slug) ?? abort(404);

        return $this->cached(['suggestions' => app(\App\Services\Storefront\ProductSearch::class)->suggest($this->stores->products($company), (string) $request->input('q', ''))]);
    }

    /** A seller's page inside the market ("Sold by ..."): who they are and what they sell here. A seller has no store of its own. */
    public function seller(Request $request, string $slug, int $id)
    {
        $request->validate(['page' => 'nullable|integer|min:1|max:1000']);
        $company = $this->stores->find($slug) ?? abort(404);
        $seller = \App\Models\Company::withoutGlobalScopes()->where('tenant_id', $company->tenant_id)->where('type', 'supplier')->where('status', 'active')->findOrFail($id);

        $page = $this->stores->products($company)->where('seller_id', $seller->id)->orderBy('category')->orderBy('name')->paginate(12)
            ->through(fn ($p) => $this->stores->card($p, $company));

        // What the seller says about the shop, and how shoppers rated what it sold (only published reviews of its own products).
        $profile = $seller->settings['profile'] ?? [];
        $rows = \App\Models\ProductReview::published()->whereIn('product_id', \App\Models\Product::withoutGlobalScopes()->where('seller_id', $seller->id)->select('id'))->selectRaw('count(*) as c, avg(rating) as a')->first();
        $delivered = \App\Models\Order::withoutGlobalScopes()->whereIn('product_id', \App\Models\Product::withoutGlobalScopes()->where('seller_id', $seller->id)->select('id'))->where('status', 'completed')->count();

        return $this->cached(['seller' => ['id' => $seller->id, 'name' => $seller->name, 'city' => $profile['city'] ?? null, 'since' => $seller->created_at?->format('Y-m'),
            'about' => $profile['about'] ?? null, 'logo' => $profile['logo'] ?? null, 'hours' => $profile['hours'] ?? null, 'ships_within_days' => $profile['ships_within_days'] ?? null,
            'rating' => ['count' => (int) ($rows->c ?? 0), 'average' => ($rows->c ?? 0) > 0 ? round((float) $rows->a, 1) : null], 'delivered_orders' => $delivered], 'store' => ['name' => $company->name, 'slug' => $company->slug], 'products' => $page]);
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

    /** Every category's name in both languages, keyed by the slug products carry, so any screen can show it in the shopper's language. */
    public function categoryNames()
    {
        return response()->json(\App\Models\ProductCategory::where('is_active', true)->get()->mapWithKeys(fn ($c) => [$c->key => ['en' => $c->name_en, 'ar' => $c->name_ar]]))
            ->header('Cache-Control', 'public, max-age=300');
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
            'product' => $this->stores->card($product, $company) + ['fits_note' => $product->attributes['fits_note'] ?? null, 'bundle_items' => $product->bundleItems()->with('product:id,name,name_ar,name_ar,description,description_ar,brand,base_price,images,warranty_years')->get()->map(function ($i) {
                $ar = app()->getLocale() === 'ar';
                $p = $i->product;

                return ['id' => $i->product_id, 'name' => $ar && $p?->name_ar ? $p->name_ar : $p?->name, 'quantity' => (int) $i->quantity, 'image' => ($p?->images ?? [])[0] ?? null,
                    'description' => \Illuminate\Support\Str::limit((string) ($ar && $p?->description_ar ? $p->description_ar : $p?->description), 160), 'brand' => $p?->brand, 'price' => (float) ($p?->base_price ?? 0), 'warranty_years' => $p?->warranty_years];
            })->values()->all(), 'prices' => $this->stores->priceList($product), 'highlights' => $this->stores->highlightsOf($product), 'fitment_required' => (bool) $product->fitment_required, 'fitments' => app(\App\Services\Fitment\FitmentService::class)->describe($product) + [], 'page' => $this->pageContent($product), 'addons' => $this->addons($product, $company), 'page_template' => in_array($product->page_template, \App\Models\Product::PAGE_TEMPLATES, true) ? $product->page_template : 'classic'],
            'related' => $related,
            'recommended' => [
                'also_bought' => app(\App\Services\Storefront\ProductRecommendations::class)->alsoBought($product, $company)->map(fn ($p) => $this->stores->card($p, $company))->all(),
                'similar' => app(\App\Services\Storefront\ProductRecommendations::class)->similar($product, $company)->map(fn ($p) => $this->stores->card($p, $company))->all(),
            ],
            'reviews' => $this->reviews($product->id),
            'questions' => \App\Models\ProductQuestion::where('product_id', $product->id)->public()->with('user:id,name')->orderByDesc('answered_at')->limit(10)->get()
                ->map(fn ($q) => ['id' => $q->id, 'question' => $q->question, 'answer' => $q->answer, 'asker' => explode(' ', trim((string) $q->user?->name))[0] ?: 'Customer', 'answered_at' => $q->answered_at?->toDateString()])->all(),
            'delivery_estimate' => $product->requires_shipping ? app(\App\Services\ShippingFees::class)->estimate(app(\App\Support\Markets\Markets::class)->forCompany($company)?->code, $product->fulfilment ?? 'seller') : null,
            'customer_fee_percentage' => (float) app(\App\Support\Settings\Settings::class)->get('platform.customer_fee_percentage'),
            // What delivery costs here: the flat fee, the amount above which it is free, and the usual days.
            'shipping' => app(\App\Services\ShippingFees::class)->rules(app(\App\Support\Markets\Markets::class)->forCompany($company)?->code),
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
                'photos' => array_values($r->photos ?? []),
            ])->all(),
        ];
    }

    /** Public data may be cached by browsers and CDNs for a minute. */
    /** A checked number written into the SQL itself: bound as text, SQLite would compare it as larger than any figure. */
    private function number(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function cached($data)
    {
        // The words depend on the language of the asker, so a shared cache must keep one copy per language.
        return response()->json($data)->header('Cache-Control', 'public, max-age=60, s-maxage=60')->header('Vary', 'Accept-Language');
    }
}
