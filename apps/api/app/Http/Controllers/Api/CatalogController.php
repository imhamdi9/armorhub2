<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\IndustryRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class CatalogController extends Controller
{
    /**
     * Active records of any registered shared catalog by morph alias
     * (vehicle_part, room_type, device_model...), so one screen can
     * populate pickers for every industry.
     */
    public function index(string $alias, IndustryRegistry $registry)
    {
        $class = $this->resolve($alias, $registry);

        return $class::query()->where('is_active', true)->orderBy('name')->get();
    }

    public function store(Request $request, string $alias, IndustryRegistry $registry)
    {
        $class = $this->resolve($alias, $registry);
        $model = new $class;

        $record = $class::create($this->validated($request, $model));

        return response()->json($record, 201);
    }

    public function update(Request $request, string $alias, int $id, IndustryRegistry $registry)
    {
        $class = $this->resolve($alias, $registry);
        $record = $class::findOrFail($id);

        $record->update($this->validated($request, $record, true));

        return $record->fresh();
    }

    public function destroy(string $alias, int $id, IndustryRegistry $registry)
    {
        $this->resolve($alias, $registry)::findOrFail($id)->delete();

        return response()->json(['message' => 'Deleted']);
    }

    /** @return class-string<Model> */
    private function resolve(string $alias, IndustryRegistry $registry): string
    {
        $class = ($registry->targetModels() + $registry->subjectModels())[$alias] ?? null;

        abort_if($class === null, 404, 'Unknown catalog.');

        // Customer-owned subjects (e.g. properties) are not shared catalogs.
        abort_unless(Schema::hasColumn((new $class)->getTable(), 'is_active'), 404, 'Not a catalog.');

        return $class;
    }

    /** name + slug (unique per table) + any parent *_id keys the model requires. */
    private function validated(Request $request, Model $model, bool $partial = false): array
    {
        $req = $partial ? 'sometimes|required' : 'required';
        $table = $model->getTable();

        $rules = [
            'name' => "$req|string|max:255",
            'slug' => [
                ...($partial ? ['sometimes'] : []),
                'required', 'string', 'max:255',
                Rule::unique($table, 'slug')->ignore($model->getKey()),
            ],
            'is_active' => 'boolean',
        ];

        foreach ($model->getFillable() as $field) {
            if (str_ends_with($field, '_id')) {
                $rules[$field] = "$req|integer";
            }
        }

        return $request->validate($rules);
    }
}
