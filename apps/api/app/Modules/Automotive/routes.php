<?php

use App\Modules\Automotive\Http\Controllers\VehicleController;
use App\Modules\Automotive\Http\Controllers\VehicleMakeController;
use Illuminate\Support\Facades\Route;

Route::get('/vehicle-makes', [VehicleMakeController::class, 'index'])
    ->middleware(['auth:sanctum', 'permission:vehicle-makes.view']);

Route::post('/vehicle-makes', [VehicleMakeController::class, 'store'])
    ->middleware(['auth:sanctum', 'permission:vehicle-makes.create']);

Route::get('/vehicle-makes/{vehicle_make}', [VehicleMakeController::class, 'show'])
    ->middleware(['auth:sanctum', 'permission:vehicle-makes.view']);

Route::put('/vehicle-makes/{vehicle_make}', [VehicleMakeController::class, 'update'])
    ->middleware(['auth:sanctum', 'permission:vehicle-makes.update']);

Route::delete('/vehicle-makes/{vehicle_make}', [VehicleMakeController::class, 'destroy'])
    ->middleware(['auth:sanctum', 'permission:vehicle-makes.delete']);

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/vehicles/makes', [VehicleController::class, 'makes']);
    Route::get('/vehicles/makes/{id}/models', [VehicleController::class, 'models']);
});
