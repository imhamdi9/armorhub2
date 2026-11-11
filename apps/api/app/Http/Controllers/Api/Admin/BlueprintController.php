<?php

namespace App\Http\Controllers\Api\Admin;

use App\Core\Blueprints\BlueprintApplier;
use App\Core\Blueprints\BlueprintError;
use App\Core\Blueprints\BlueprintRegistry;
use App\Core\Blueprints\Models\BlueprintApplication;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Launch a new market (or any business a module knows how to launch) from a blueprint. */
class BlueprintController extends Controller
{
    public function index(BlueprintRegistry $blueprints)
    {
        return ['blueprints' => collect($blueprints->all())->map->toArray()->values()];
    }

    public function apply(Request $request, string $id, BlueprintApplier $applier, BlueprintRegistry $blueprints)
    {
        abort_unless($blueprints->get($id), 404);
        $params = $request->validate(['params' => 'present|array'])['params'];

        try {
            $application = $applier->apply($id, $params, $request->user()->id);
        } catch (BlueprintError $e) {
            throw ValidationException::withMessages(['params' => [$e->getMessage()]]);
        }

        Audit::record('platform.blueprint_applied', null, ['blueprint' => $id, 'application' => $application->id, 'tenant_id' => $application->tenant_id]);

        return response()->json($this->present($application), $application->wasRecentlyCreated ? 201 : 200);
    }

    public function history()
    {
        return ['applications' => BlueprintApplication::latest('id')->limit(50)->get()->map(fn ($a) => $this->present($a))];
    }

    private function present(BlueprintApplication $a): array
    {
        $slug = collect($a->outputs ?? [])->pluck('slug')->filter()->first();

        return ['id' => $a->id, 'blueprint_id' => $a->blueprint_id, 'tenant_id' => $a->tenant_id, 'params' => $a->params, 'store_slug' => $slug, 'created_at' => $a->created_at];
    }
}
