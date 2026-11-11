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
            ->when($request->filled('industry_id'), fn ($q) => $q->where('industry_id', $request->industry_id))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->category))
            ->when(! $request->boolean('include_inactive'), fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->get();
    }

    public function store(Request $request)
    {
        $product = Product::create($this->validated($request));

        return response()->json($product, 201);
    }

    public function show(Product $product)
    {
        return $product->load('priceRules');
    }

    public function update(Request $request, Product $product)
    {
        $product->update($this->validated($request, $product));

        return $product->fresh();
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return response()->json(['message' => 'Deleted']);
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        $sometimes = $product ? 'sometimes|' : '';

        return $request->validate([
            'industry_id' => 'nullable|exists:industries,id',
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
        ]);
    }
}
