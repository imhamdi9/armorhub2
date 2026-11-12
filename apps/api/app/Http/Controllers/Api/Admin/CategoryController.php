<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** The platform's product taxonomy: groups and their categories, named in English and Arabic. */
class CategoryController extends Controller
{
    public function index()
    {
        $counts = Product::withoutGlobalScopes()->selectRaw('category, count(*) c')->groupBy('category')->pluck('c', 'category');
        $rows = ProductCategory::orderBy('position')->orderBy('name_en')->get();

        return $rows->whereNull('parent_id')->map(fn ($g) => $this->row($g, $counts) + [
            'children' => $rows->where('parent_id', $g->id)->values()->map(fn ($c) => $this->row($c, $counts)),
        ])->values();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/', Rule::unique('product_categories', 'key')],
            'name_en' => 'required|string|max:120',
            'name_ar' => 'required|string|max:120',
            'parent_id' => ['nullable', Rule::exists('product_categories', 'id')->whereNull('parent_id')],
            'position' => 'nullable|integer|min:0|max:10000',
        ]);
        $category = ProductCategory::create($data);
        Audit::record('category.created', $category, ['key' => $category->key], $request->user());

        return response()->json($this->row($category, collect()), 201);
    }

    public function update(Request $request, ProductCategory $category)
    {
        $data = $request->validate([
            'name_en' => 'sometimes|required|string|max:120',
            'name_ar' => 'sometimes|required|string|max:120',
            'position' => 'nullable|integer|min:0|max:10000',
            'is_active' => 'sometimes|boolean',
        ]);

        // Hiding a category that products still use would leave them unlisted: move them first.
        if (($data['is_active'] ?? true) === false && Product::withoutGlobalScopes()->where('category', $category->key)->exists()) {
            throw ValidationException::withMessages(['is_active' => ['Products still use this category. Move them to another one first.']]);
        }
        $category->update($data);
        Audit::record('category.updated', $category, $data, $request->user());

        return $this->row($category->fresh(), collect());
    }

    private function row(ProductCategory $c, $counts): array
    {
        return ['id' => $c->id, 'key' => $c->key, 'name_en' => $c->name_en, 'name_ar' => $c->name_ar, 'parent_id' => $c->parent_id,
            'position' => $c->position, 'is_active' => $c->is_active, 'products' => (int) ($counts[$c->key] ?? 0)];
    }
}
