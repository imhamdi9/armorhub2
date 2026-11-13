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
    /** The whole tree, any depth: each category with its sub-categories (and theirs). */
    public function index()
    {
        $counts = Product::withoutGlobalScopes()->selectRaw('category, count(*) c')->groupBy('category')->pluck('c', 'category');
        $rows = ProductCategory::orderBy('position')->orderBy('name_en')->get()->groupBy(fn ($c) => $c->parent_id ?? 0);

        $build = function ($parent) use (&$build, $rows, $counts) {
            return ($rows[$parent] ?? collect())->values()->map(fn ($c) => $this->row($c, $counts) + ['children' => $build($c->id)])->all();
        };

        return $build(0);
    }

    /** A key made from the English name, made unique when it is taken. */
    private function makeKey(string $name): string
    {
        $base = \Illuminate\Support\Str::slug($name) ?: 'category';
        $key = $base;
        for ($i = 2; ProductCategory::where('key', $key)->exists(); $i++) {
            $key = "{$base}-{$i}";
        }

        return $key;
    }

    /** @return list<int> the category and everything below it */
    private function branch(int $id): array
    {
        $ids = [$id];
        $frontier = [$id];
        while ($frontier) {
            $frontier = ProductCategory::whereIn('parent_id', $frontier)->pluck('id')->all();
            $ids = array_merge($ids, $frontier);
        }

        return $ids;
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'key' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/', Rule::unique('product_categories', 'key')],
            'name_en' => 'required|string|max:120',
            'name_ar' => 'required|string|max:120',
            'parent_id' => ['nullable', 'integer', Rule::exists('product_categories', 'id')],
            'position' => 'nullable|integer|min:0|max:10000',
        ]);
        $data['key'] = $data['key'] ?? $this->makeKey($data['name_en']);
        if (app(\App\Services\CatalogChanges::class)->mustWait($request->user())) {
            return $this->proposed(app(\App\Services\CatalogChanges::class)->propose($request->user(), 'category', 'create', null, $data, "Add category \"{$data['name_en']}\""));
        }
        $category = ProductCategory::create($data);
        Audit::record('category.created', $category, ['key' => $category->key], $request->user());

        return response()->json($this->row($category, collect()), 201);
    }

    /** Rename (English and Arabic), move under another category, reorder, hide or show. The key stays: products are filed by it. */
    public function update(Request $request, ProductCategory $category)
    {
        $data = $request->validate([
            'name_en' => 'sometimes|required|string|max:120',
            'name_ar' => 'sometimes|required|string|max:120',
            'parent_id' => ['sometimes', 'nullable', 'integer', Rule::exists('product_categories', 'id')],
            'position' => 'nullable|integer|min:0|max:10000',
            'is_active' => 'sometimes|boolean',
        ]);

        // A category cannot move under itself or under one of its own sub-categories.
        if (! empty($data['parent_id']) && in_array((int) $data['parent_id'], $this->branch($category->id), true)) {
            throw ValidationException::withMessages(['parent_id' => ['A category cannot be placed under itself or under one of its own sub-categories.']]);
        }
        // Hiding a category that products still use would leave them unlisted: move them first.
        if (($data['is_active'] ?? true) === false && Product::withoutGlobalScopes()->where('category', $category->key)->exists()) {
            throw ValidationException::withMessages(['is_active' => ['Products still use this category. Move them to another one first.']]);
        }
        if (app(\App\Services\CatalogChanges::class)->mustWait($request->user())) {
            return $this->proposed(app(\App\Services\CatalogChanges::class)->propose($request->user(), 'category', 'update', $category->id, $data, "Change category \"{$category->name_en}\""));
        }
        $category->update($data);
        Audit::record('category.updated', $category, $data, $request->user());

        return $this->row($category->fresh(), collect());
    }

    private function proposed(\App\Models\CatalogChange $c)
    {
        return response()->json(['pending' => true, 'change_id' => $c->id, 'message' => 'Sent to the platform admin for approval. It goes live once approved.'], 202);
    }

    private function row(ProductCategory $c, $counts): array
    {
        return ['id' => $c->id, 'key' => $c->key, 'name_en' => $c->name_en, 'name_ar' => $c->name_ar, 'parent_id' => $c->parent_id,
            'position' => $c->position, 'is_active' => $c->is_active, 'products' => (int) ($counts[$c->key] ?? 0)];
    }
}
