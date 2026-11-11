<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\IndustryRegistry;
use Illuminate\Http\Request;

/**
 * Items a customer brings to be serviced (their property, vehicle...),
 * for subject models that are customer-owned (have a customer_id).
 */
class MyItemsController extends Controller
{
    public function index(Request $request, string $alias, IndustryRegistry $registry)
    {
        return $this->resolve($alias, $registry)::where('customer_id', $request->user()->id)->latest()->get();
    }

    public function store(Request $request, string $alias, IndustryRegistry $registry)
    {
        $class = $this->resolve($alias, $registry);
        $model = new $class;

        $rules = [];
        foreach ($model->getFillable() as $field) {
            if ($field === 'customer_id') {
                continue;
            }
            $rules[$field] = ($model->getCasts()[$field] ?? null) !== null && str_starts_with($model->getCasts()[$field], 'decimal')
                ? 'nullable|numeric|min:0'
                : 'nullable|string|max:255';
        }

        $item = $class::create($request->validate($rules) + ['customer_id' => $request->user()->id]);

        return response()->json($item, 201);
    }

    public function destroy(Request $request, string $alias, int $id, IndustryRegistry $registry)
    {
        $this->resolve($alias, $registry)::where('customer_id', $request->user()->id)->findOrFail($id)->delete();

        return response()->json(['message' => 'Deleted']);
    }

    private function resolve(string $alias, IndustryRegistry $registry): string
    {
        $class = $registry->subjectModels()[$alias] ?? null;

        abort_if($class === null || ! in_array('customer_id', (new $class)->getFillable(), true), 404, 'Unknown item type.');

        return $class;
    }
}
