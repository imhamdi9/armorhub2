<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Industry;
use App\Modules\IndustryRegistry;

class IndustryController extends Controller
{
    /**
     * Active industries with the subject/target aliases each one supports,
     * so clients can adapt their UI to the tenant's industry.
     */
    public function index(IndustryRegistry $registry)
    {
        return Industry::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function (Industry $industry) use ($registry) {
                $module = $industry->module();

                return [
                    'id' => $industry->id,
                    'key' => $industry->key,
                    'name' => $industry->name,
                    'description' => $industry->description,
                    'subjects' => array_keys($module?->subjectModels() ?? []),
                    'targets' => array_keys($module?->targetModels() ?? []),
                    'catalogs' => $module ? $registry->manageableAliases($module) : [],
                ];
            })
            ->values();
    }
}
