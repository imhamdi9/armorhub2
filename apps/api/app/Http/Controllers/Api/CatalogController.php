<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\IndustryRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CatalogController extends Controller
{
    public function __construct(private \App\Services\CatalogChanges $changes)
    {
    }

    private function proposed(\App\Models\CatalogChange $c)
    {
        return response()->json(['pending' => true, 'change_id' => $c->id, 'message' => 'Sent to the platform admin for approval. It goes live once approved.'], 202);
    }

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

    /** Parent lists a record needs (e.g. a vehicle model needs a vehicle make). */
    public function schema(string $alias, IndustryRegistry $registry)
    {
        $class = $this->resolve($alias, $registry);
        $known = array_keys($registry->sharedCatalogs());

        $parents = collect((new $class)->getFillable())
            ->filter(fn (string $f) => str_ends_with($f, '_id') && in_array(substr($f, 0, -3), $known, true))
            ->map(fn (string $f) => ['field' => $f, 'alias' => substr($f, 0, -3)])
            ->values();

        return ['alias' => $alias, 'parents' => $parents];
    }

    public function store(Request $request, string $alias, IndustryRegistry $registry)
    {
        $class = $this->resolve($alias, $registry);
        $model = new $class;
        $data = $this->validated($request, $model);

        // A market admin only proposes: it goes live when the platform admin approves.
        if ($this->changes->mustWait($request->user())) {
            return $this->proposed($this->changes->propose($request->user(), "catalog:{$alias}", 'create', null, $data, "Add \"{$data['name']}\" to {$alias}"));
        }

        return response()->json($class::create($data), 201);
    }

    public function update(Request $request, string $alias, int $id, IndustryRegistry $registry)
    {
        $class = $this->resolve($alias, $registry);
        $record = $class::findOrFail($id);
        $data = $this->validated($request, $record, true);

        if ($this->changes->mustWait($request->user())) {
            return $this->proposed($this->changes->propose($request->user(), "catalog:{$alias}", 'update', $id, $data, "Change \"{$record->name}\" in {$alias}"));
        }
        $record->update($data);

        return $record->fresh();
    }

    public function destroy(Request $request, string $alias, int $id, IndustryRegistry $registry)
    {
        $record = $this->resolve($alias, $registry)::findOrFail($id);

        if ($this->changes->mustWait($request->user())) {
            return $this->proposed($this->changes->propose($request->user(), "catalog:{$alias}", 'delete', $id, [], "Delete \"{$record->name}\" from {$alias}"));
        }

        // A subject (vehicle model, device model...) that orders point at is part of their history.
        if (isset($registry->subjectModels()[$alias])
            && \App\Models\Order::withoutGlobalScopes()->where('subject_type', $alias)->where('subject_id', $id)->exists()) {
            return response()->json(['message' => 'This item is used by existing orders and cannot be deleted.'], 422);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($record, $alias, $id) {
            // Price rules pointing at a deleted target would be orphans.
            \App\Models\ProductPriceRule::where('target_type', $alias)->where('target_id', $id)->delete();
            $record->delete();
        });

        return response()->json(['message' => 'Deleted']);
    }

    /** @return class-string<Model> */
    private function resolve(string $alias, IndustryRegistry $registry): string
    {
        $class = $registry->sharedCatalogs()[$alias] ?? null;

        abort_if($class === null, 404, 'Unknown catalog.');

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
