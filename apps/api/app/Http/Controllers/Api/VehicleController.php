<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VehicleMake;


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