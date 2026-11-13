<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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

    /** The platform's category tree (groups with their categories), so forms offer a fixed list and nobody types a category. */
    public function categories(Request $request)
    {
        $rows = \App\Models\ProductCategory::where('is_active', true)->orderBy('position')->orderBy('name_en')->get()->groupBy(fn ($c) => $c->parent_id ?? 0);

        $build = function ($parent) use (&$build, $rows) {
            return ($rows[$parent] ?? collect())->values()->map(fn ($c) => ['key' => $c->key, 'name' => $c->name_en, 'name_ar' => $c->name_ar, 'children' => $build($c->id)])->all();
        };

        return $build(0);
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
                'industry_id' => $this->sellerIndustry($request, $company),
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

        // Stock kept by ArmorHub changes only through the warehouse's movements, never by editing the product.
        if (($data['fulfilment'] ?? $product->fulfilment) === 'armorhub') {
            unset($data['stock_cartons']);
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

    /** A seller's product belongs to one industry of its market: the one it chose, or the market's only industry. */
    private function sellerIndustry(Request $request, $company): ?int
    {
        $allowed = $company->tenant?->industryIds() ?? [];

        // A market with one industry has nothing to choose; a country market with several asks the seller which one.
        if (count($allowed) <= 1) {
            return $allowed[0] ?? null;
        }

        $chosen = $request->integer('industry_id') ?: null;
        if ($chosen === null) {
            throw ValidationException::withMessages(['industry_id' => ['Choose the industry of this product.']]);
        }
        if (! in_array($chosen, $allowed, true)) {
            throw ValidationException::withMessages(['industry_id' => ['This market does not run that industry.']]);
        }

        return $chosen;
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

        $industries = $user->industryIds();
        $market = app(\App\Support\Markets\Markets::class)->forCompany($user->company)?->code;

        $query->where(fn ($q) => $q->whereNull('industry_id')->when($industries, fn ($w) => $w->orWhereIn('industry_id', $industries)));
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

        if ($request->filled('sku')) {
            $request->merge(['sku' => strtoupper(trim((string) $request->input('sku')))]);
        }

        $rules = [
            'industry_id' => 'nullable|exists:industries,id',
            'market' => ['nullable', 'string', Rule::in(app(\App\Support\Markets\Markets::class)->codes())],
            'category' => [...($product ? ['sometimes'] : []), 'required', 'string', 'max:80', Rule::exists('product_categories', 'key')->where('is_active', true)],
            'sku' => ['nullable', 'string', 'min:3', 'max:40', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('products', 'sku')->ignore($product?->id)],
            'units_per_carton' => 'nullable|integer|min:1|max:100000',
            'carton_length_cm' => 'nullable|numeric|min:0.1|max:1000',
            'carton_width_cm' => 'nullable|numeric|min:0.1|max:1000',
            'carton_height_cm' => 'nullable|numeric|min:0.1|max:1000',
            'carton_weight_kg' => 'nullable|numeric|min:0.001|max:2000',
            'stock_cartons' => 'nullable|integer|min:0|max:10000000',
            'fulfilment' => 'sometimes|in:seller,armorhub',
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
            unset($rules['market']);
            $rules['industry_id'] = 'nullable|integer';
        }

        $data = $request->validate($rules);
        $this->checkFulfilment($data, $request, $product);

        return $data;
    }

    /** ArmorHub can only store and ship what it can handle: a physical product with complete carton data, in a market that has a warehouse. */
    private function checkFulfilment(array $data, Request $request, ?Product $product): void
    {
        $wants = $data['fulfilment'] ?? $product?->fulfilment ?? 'seller';
        $stock = app(\App\Services\Warehouse\WarehouseService::class);

        if ($wants === 'seller') {
            if ($product && ($data['fulfilment'] ?? null) === 'seller' && $product->fulfilment === 'armorhub' && $stock->unitsOf($product) > 0) {
                throw ValidationException::withMessages(['fulfilment' => ['Units are still in the warehouse. Ask the warehouse to return them first.']]);
            }

            return;
        }

        $get = fn ($k) => array_key_exists($k, $data) ? $data[$k] : $product?->{$k};
        if (! ($get('requires_shipping') ?? $request->boolean('requires_shipping', true))) {
            throw ValidationException::withMessages(['fulfilment' => ['Only physical products can be stored by ArmorHub.']]);
        }
        foreach (['units_per_carton', 'carton_length_cm', 'carton_width_cm', 'carton_height_cm', 'carton_weight_kg'] as $k) {
            if (! $get($k)) {
                throw ValidationException::withMessages(['fulfilment' => ['Fill in the carton size, weight and units per carton first: the warehouse needs them.']]);
            }
        }
        $company = $product?->seller_id ? \App\Models\Company::withoutGlobalScopes()->find($product->seller_id) : $request->user()->company;
        if (! $company?->tenant_id || ! $stock->warehouseFor($company->tenant_id)) {
            throw ValidationException::withMessages(['fulfilment' => ['This market has no open warehouse yet.']]);
        }
    }
}
