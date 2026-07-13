<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\PricingController;


Route::post(
    '/pricing/calculate',
    [PricingController::class,'calculate']
);

Route::get(
    '/vehicles/makes',
    [VehicleController::class,'makes']
);


Route::get(
    '/vehicles/makes/{id}/models',
    [VehicleController::class,'models']
);