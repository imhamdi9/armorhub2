<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\IndustryRegistry;
use Illuminate\Support\Facades\Schema;

class CatalogController extends Controller
{
    /**
     * Active records of any registered target/subject model by morph alias
     * (e.g. vehicle_part, room_type, device_model), so one screen can
     * populate pickers for every industry.
     */
    public function index(string $alias, IndustryRegistry $registry)
    {
        $class = ($registry->targetModels() + $registry->subjectModels())[$alias] ?? null;

        abort_if($class === null, 404, 'Unknown catalog.');

        $model = new $class;

        // Customer-owned subjects (e.g. properties) are not shared catalogs.
        abort_unless(Schema::hasColumn($model->getTable(), 'is_active'), 404, 'Not a catalog.');

        return $class::query()->where('is_active', true)->orderBy('name')->get();
    }
}
