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
            ->whereNotNull('slug')->whereNotIn('status', ['inactive', 'suspended'])
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

    /** The countries the platform operates in, with their currency, VAT and phone rules. */
    public function markets(\App\Support\Markets\Markets $markets)
    {
        return $this->cached(['markets' => collect($markets->all())->map->toArray()->values()]);
    }

    /** Home page: partners and products, one block per industry. */
    public function showcase()
    {
        return $this->cached(['industries' => $this->stores->showcase()]);
    }

    public function show(string $slug)
    {
        $company = $this->stores->find($slug) ?? abort(404);
        $settings = $this->stores->settingsOf($company);

        $featured = collect($settings['featured_product_ids'])->isNotEmpty()
            ? $this->stores->products($company)->whereIn('id', $settings['featured_product_ids'])->get()
            : $this->stores->products($company)->orderBy('id')->limit(4)->get();

        return $this->cached([
            'store' => $this->stores->branding($company),
            'industry' => $this->stores->industry($company),
            'market' => $this->stores->market($company),
            'featured' => $featured->map(fn ($p) => $this->stores->card($p, $company))->values(),
            'categories' => $this->stores->products($company)->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'partners' => $this->stores->partnerSummary($company),
        ]);
    }

    public function products(Request $request, string $slug)
    {
        $request->validate(['q' => 'nullable|string|max:80', 'category' => 'nullable|string|max:80', 'page' => 'nullable|integer|min:1|max:1000']);
        $company = $this->stores->find($slug) ?? abort(404);

        $page = $this->stores->products($company)
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', '%'.addcslashes($request->q, '%_\\').'%')
                ->orWhere('brand', 'like', '%'.addcslashes($request->q, '%_\\').'%')))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->category))
            ->orderBy('name')
            ->paginate(12)
            ->through(fn ($p) => $this->stores->card($p, $company));

        return $this->cached($page);
    }

    public function product(string $slug, int $id)
    {
        $company = $this->stores->find($slug) ?? abort(404);
        $product = $this->stores->products($company)->findOrFail($id);

        return $this->cached([
            'product' => $this->stores->card($product, $company) + ['prices' => $this->stores->priceList($product)],
            'store' => ['name' => $company->name, 'slug' => $company->slug],
        ]);
    }

    /** Public data may be cached by browsers and CDNs for a minute. */
    private function cached($data)
    {
        return response()->json($data)->header('Cache-Control', 'public, max-age=60, s-maxage=60');
    }
}
