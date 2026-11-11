<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
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
                ! ($request->boolean('include_inactive') && $request->user()->can('products.update')),
                fn ($q) => $q->where('is_active', true)
            )
            ->orderBy('name')
            ->get();
    }

    public function store(Request $request)
    {
        $product = Product::create($this->validated($request));

        return response()->json($product, 201);
    }

    public function show(Request $request, Product $product)
    {
        abort_unless($this->visibleTo($request->user(), Product::whereKey($product->id))->exists(), 404);

        return $product->load('priceRules');
    }

    public function update(Request $request, Product $product)
    {
        $product->update($this->validated($request, $product));

        return $product->fresh();
    }

    public function destroy(Product $product)
    {
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

        return $request->validate([
            'industry_id' => 'nullable|exists:industries,id',
            'market' => ['nullable', 'string', Rule::in(app(\App\Support\Markets\Markets::class)->codes())],
            'category' => 'nullable|string|max:255',
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
            'is_active' => 'boolean',
            'requires_shipping' => 'boolean',
        ]);
    }
}
