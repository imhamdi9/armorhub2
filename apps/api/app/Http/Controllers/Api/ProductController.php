<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    /** A seller manages only its own products; everyone else (admins, store owners) the shared catalog. */
    private function isSeller($user): bool
    {
        return $user->hasRole('seller') && ! $user->hasRole('platform-admin');
    }

    /** Sellers' products are theirs alone to change; the shared catalog is changed by admins and store owners. */
    private function mayChange($user, Product $product): bool
    {
        if ($user->hasRole('platform-admin')) {
            return true;
        }

        return $this->isSeller($user) ? $product->seller_id === $user->company_id : $product->seller_id === null;
    }

    public function index(Request $request)
    {
        return Product::query()
            ->tap(fn ($q) => $this->visibleTo($request->user(), $q))
            ->when($request->filled('industry_id'), fn ($q) => $q->where('industry_id', $request->industry_id))
            ->when($request->filled('search'), fn ($q) => $q->where(
                fn ($w) => $w->where('name', 'like', "%{$request->search}%")->orWhere('brand', 'like', "%{$request->search}%")
            ))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->category))
            // Only product managers may see inactive products.
            ->when(
                ! $this->isSeller($request->user()) && ! ($request->boolean('include_inactive') && $request->user()->can('products.update')),
                fn ($q) => $q->where('is_active', true)
            )
            ->withCount('priceRules')
            ->orderBy('name')
            ->get();
    }

    /** The categories already used by the products this person can see, so forms offer a tidy list instead of free typing. */
    public function categories(Request $request)
    {
        return Product::query()
            ->tap(fn ($q) => $this->visibleTo($request->user(), $q))
            ->whereNotNull('category')->where('category', '!=', '')
            ->distinct()->orderBy('category')->pluck('category')->values();
    }

    /**
     * Sets how products come with installation (none / optional / required) for many at once. Only the products this person may change are
     * touched; the rest are reported back as skipped, never silently changed.
     */
    public function bulkInstallation(Request $request)
    {
        $data = $request->validate(['ids' => 'required|array|min:1|max:500', 'ids.*' => 'integer', 'installation' => 'required|in:none,optional,required']);
        $user = $request->user();
        $products = Product::query()->tap(fn ($q) => $this->visibleTo($user, $q))->whereIn('id', $data['ids'])->get();

        $updated = 0;
        foreach ($products as $product) {
            if ($this->mayChange($user, $product)) {
                $product->update(['installation' => $data['installation']]);
                $updated++;
            }
        }
        \App\Support\Audit::record('products.bulk_installation', null, ['installation' => $data['installation'], 'updated' => $updated], $user);

        return response()->json(['updated' => $updated, 'skipped' => count($data['ids']) - $updated]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $user = $request->user();

        if ($this->isSeller($user)) {
            // A seller's products live in its own marketplace: the industry and country come from there, never from the form.
            $company = $user->company()->with('tenant')->first();
            $data = array_merge($data, [
                'seller_id' => $company->id,
                'industry_id' => $company->tenant?->industry_id,
                'market' => app(\App\Support\Markets\Markets::class)->forCompany($company)?->code,
                'requires_shipping' => $request->boolean('requires_shipping', true),
            ]);
        }

        return response()->json(Product::create($data), 201);
    }

    public function show(Request $request, Product $product)
    {
        abort_unless($this->visibleTo($request->user(), Product::whereKey($product->id))->exists(), 404);

        // Whether the signed-in person may change it: the screen shows an editor only to those who can, instead of buttons that fail.
        return $product->load('priceRules')->toArray() + ['can_edit' => $request->user()->can('products.update') && $this->mayChange($request->user(), $product)];
    }

    public function update(Request $request, Product $product)
    {
        abort_unless($this->mayChange($request->user(), $product), 404);
        $data = $this->validated($request, $product);

        if ($this->isSeller($request->user())) {
            unset($data['industry_id'], $data['market'], $data['seller_id']);
        }

        $product->update($data);

        // The same shape as show(), so a screen that replaces its copy with this one does not lose the edit permission.
        return $product->fresh()->toArray() + ['can_edit' => true];
    }

    public function destroy(Request $request, Product $product)
    {
        abort_unless($this->mayChange($request->user(), $product), 404);

        // Orders are financial records; never delete them by deleting a product.
        if (\App\Models\Order::withoutGlobalScopes()->where('product_id', $product->id)->exists()) {
            return response()->json([
                'message' => 'This product has orders and cannot be deleted. Hide it from customers instead.',
            ], 422);
        }

        $product->delete();

        return response()->json(['message' => 'Deleted']);
    }

    /**
     * Platform admins see everything. Everyone else sees only products of their own industry
     * (or industry-less ones); hiding a product also hides it from non-managers.
     */
    private function visibleTo($user, $query)
    {
        if ($user->hasRole('platform-admin')) {
            return $query;
        }

        // A seller sees its own products and nothing else.
        if ($this->isSeller($user)) {
            return $query->where('seller_id', $user->company_id);
        }

        // Everyone else sees the shared catalog and the products of the active sellers in their marketplace.
        $query->visibleInTenant($user->company?->tenant_id);

        $industry = $user->industryId();
        $market = app(\App\Support\Markets\Markets::class)->forCompany($user->company)?->code;

        $query->where(fn ($q) => $q->whereNull('industry_id')->when($industry, fn ($w) => $w->orWhere('industry_id', $industry)));
        // A product limited to one country is only shown in that country.
        $query->where(fn ($q) => $q->whereNull('market')->when($market, fn ($w) => $w->orWhere('market', $market)));

        if (! $user->can('products.update')) {
            $query->where('is_active', true);
        }

        return $query;
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        $sometimes = $product ? 'sometimes|' : '';

        // One spelling per category ("Skin Care", "skin care" and "skin-care" are the same one), and never empty.
        if ($request->has('category')) {
            $request->merge(['category' => \Illuminate\Support\Str::slug((string) $request->input('category'))]);
        }

        $rules = [
            'industry_id' => 'nullable|exists:industries,id',
            'market' => ['nullable', 'string', Rule::in(app(\App\Support\Markets\Markets::class)->codes())],
            'category' => $sometimes.'required|string|max:80',
            'brand' => 'nullable|string|max:255',
            'name' => $sometimes.'required|string|max:255',
            'slug' => [
                ...($product ? ['sometimes'] : []),
                'required', 'string', 'max:255',
                Rule::unique('products', 'slug')->ignore($product?->id),
            ],
            'description' => 'nullable|string',
            'base_price' => $sometimes.'required|numeric|min:0',
            'warranty_years' => 'nullable|integer|min:0',
            'attributes' => 'nullable|array',
            'attributes.highlights' => 'nullable|array|max:8',
            'attributes.highlights.*' => 'string|max:120',
            'images' => 'nullable|array|max:8',
            'images.*' => ['string', 'max:500', fn ($attr, $value, $fail) => \App\Support\ImageRef::valid($value) || $fail('Each image must be an https address or an uploaded image.')],
            'compare_at_price' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
            'requires_shipping' => 'boolean',
            // Whether the buyer may need a centre to fit it: none, optional or required. The product is always sold on its own.
            'installation' => 'sometimes|in:none,optional,required',
        ];

        // A seller cannot choose the industry or country: they come from its marketplace (set in store()).
        if ($this->isSeller($request->user())) {
            unset($rules['industry_id'], $rules['market']);
        }

        return $request->validate($rules);
    }
}
