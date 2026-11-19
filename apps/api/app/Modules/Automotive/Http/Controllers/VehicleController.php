<?php

namespace App\Modules\Automotive\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Automotive\Models\VehicleMake;


class VehicleController extends Controller
{

    public function makes()
    {
        return VehicleMake::query()
            ->where('is_active', true)
            ->with('models')
            ->get();
    }


    public function models($makeId)
    {
        return VehicleMake::findOrFail($makeId)
            ->models()
            ->where('is_active', true)
            ->get();
    }


}