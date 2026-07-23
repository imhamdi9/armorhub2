<?php

use Illuminate\Http\Request;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\FilmController;
use App\Http\Controllers\Api\VehicleMakeController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\PricingController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\InstallationCenterController;
use App\Http\Controllers\Api\InstallationQuoteController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\CommissionController;
use App\Http\Controllers\Api\CommissionDistributionController;
use App\Http\Controllers\Api\CommissionDashboardController;
use App\Http\Controllers\Api\InstallerOrderController;


Route::middleware([
    'auth:sanctum',
    'role:installer-manager'
])->group(function () {

    Route::get(
        '/installer/orders',
        [InstallerOrderController::class, 'index']
    );

});
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/me', [AuthController::class, 'me']);

    Route::post('/logout', [AuthController::class, 'logout']);
    
    Route::get('/orders', [
        \App\Http\Controllers\Api\OrderController::class,
        'index'
    ]);

    Route::get('/orders/{order}', [
        \App\Http\Controllers\Api\OrderController::class,
        'show'
    ]);
});

Route::middleware('auth:sanctum')->get('/me', function (Illuminate\Http\Request $request) {
    return response()->json($request->user());
});

Route::middleware('auth:sanctum')->get('/test-auth', function (Request $request) {
    return $request->user();
});


Route::middleware([
    'auth:sanctum',
    'permission:companies.view'
])->get('/companies', [CompanyController::class, 'index']);

Route::middleware([
    'auth:sanctum',
    'permission:companies.create'
])->post('/companies', [CompanyController::class, 'store']);

Route::middleware([
    'auth:sanctum',
    'permission:companies.view'
])->get('/companies/{company}', [CompanyController::class, 'show']);

Route::middleware([
    'auth:sanctum',
    'permission:companies.update'
])->put('/companies/{company}', [CompanyController::class, 'update']);

Route::middleware([
    'auth:sanctum',
    'permission:companies.delete'
])->delete('/companies/{company}', [CompanyController::class, 'destroy']);
Route::get('/films', [FilmController::class, 'index'])
    ->middleware(['auth:sanctum', 'permission:films.view']);

Route::post('/films', [FilmController::class, 'store'])
    ->middleware(['auth:sanctum', 'permission:films.create']);

Route::get('/films/{film}', [FilmController::class, 'show'])
    ->middleware(['auth:sanctum', 'permission:films.view']);

Route::put('/films/{film}', [FilmController::class, 'update'])
    ->middleware(['auth:sanctum', 'permission:films.update']);

Route::delete('/films/{film}', [FilmController::class, 'destroy'])
    ->middleware(['auth:sanctum', 'permission:films.delete']);

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
    
Route::middleware([
    'auth:sanctum',
    'role:installer-manager'
])->group(function () {

    Route::patch(
    '/installation-quotes/{installationQuote}',
    [InstallerOrderController::class, 'submitQuote']
);

    Route::apiResource(
        'installation-centers',
        InstallationCenterController::class
    );

});

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/orders', [OrderController::class, 'store']);

});

Route::get(
    '/dashboard/commissions',
    [CommissionDashboardController::class, 'commissions']
)->middleware([
    'auth:sanctum',
    'permission:dashboard.view'
]);


Route::get(
    '/commission-distributions',
    [CommissionDistributionController::class, 'index']
);

Route::get(
    '/dashboard/earnings',
    [CommissionDashboardController::class, 'earnings']
)->middleware([
    'auth:sanctum',
    'permission:dashboard.view'
]);

Route::get('/commissions', [CommissionController::class, 'index'])
    ->middleware([
        'auth:sanctum',
        'permission:commissions.view'
    ]);

Route::post(
    '/orders/{order}/payments',
    [PaymentController::class, 'store']
)->middleware([
    'auth:sanctum',
    'permission:payments.create'
]);

Route::get('/commissions/{id}', [CommissionController::class, 'show'])
    ->middleware([
        'auth:sanctum',
        'permission:commissions.view'
    ]);

Route::get(
    '/orders/{order}/installation-quotes',
    [InstallationQuoteController::class, 'index']
);

Route::get('/installation-centers', [InstallationCenterController::class,'index'])
    ->middleware([
        'auth:sanctum',
        'permission:installation-centers.view'
    ]);

Route::post('/installation-centers', [InstallationCenterController::class,'store'])
    ->middleware([
        'auth:sanctum',
        'permission:installation-centers.create'
    ]);

Route::post('/pricing/calculate', [PricingController::class,'calculate'])
    ->middleware([
        'auth:sanctum',
        'permission:pricing.calculate'
    ]);

Route::patch(
    '/installation-quotes/{installationQuote}/accept',
    [InstallationQuoteController::class, 'accept']
);

Route::get(
    '/vehicles/makes',
    [VehicleController::class,'makes']
);


Route::get(
    '/vehicles/makes/{id}/models',
    [VehicleController::class,'models']
);

Route::patch(
    '/installation-quotes/{installationQuote}',
    [InstallationQuoteController::class, 'update']
);

Route::patch(
    '/commission-distributions/{commissionDistribution}/pay',
    [CommissionDistributionController::class, 'pay']
);