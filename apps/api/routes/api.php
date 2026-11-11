<?php

use Illuminate\Http\Request;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PricingController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PartnerController;
use App\Http\Controllers\Api\PartnerQuoteController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\CommissionController;
use App\Http\Controllers\Api\CommissionDistributionController;
use App\Http\Controllers\Api\CommissionDashboardController;
use App\Http\Controllers\Api\PartnerOrderController;


Route::middleware([
    'auth:sanctum',
    'role:partner-manager'
])->group(function () {

    Route::get(
        '/partner/orders',
        [PartnerOrderController::class, 'index']
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
Route::get('/products', [ProductController::class, 'index'])
    ->middleware(['auth:sanctum', 'permission:products.view']);

Route::post('/products', [ProductController::class, 'store'])
    ->middleware(['auth:sanctum', 'permission:products.create']);

Route::get('/products/{product}', [ProductController::class, 'show'])
    ->middleware(['auth:sanctum', 'permission:products.view']);

Route::put('/products/{product}', [ProductController::class, 'update'])
    ->middleware(['auth:sanctum', 'permission:products.update']);

Route::delete('/products/{product}', [ProductController::class, 'destroy'])
    ->middleware(['auth:sanctum', 'permission:products.delete']);

Route::middleware([
    'auth:sanctum',
    'role:partner-manager'
])->group(function () {

    Route::patch(
    '/partner-quotes/{partnerQuote}',
    [PartnerOrderController::class, 'submitQuote']
);

    Route::apiResource(
        'partners',
        PartnerController::class
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
    '/orders/{order}/partner-quotes',
    [PartnerQuoteController::class, 'index']
);

Route::get('/partners', [PartnerController::class,'index'])
    ->middleware([
        'auth:sanctum',
        'permission:partners.view'
    ]);

Route::post('/partners', [PartnerController::class,'store'])
    ->middleware([
        'auth:sanctum',
        'permission:partners.create'
    ]);

Route::post('/pricing/calculate', [PricingController::class,'calculate'])
    ->middleware([
        'auth:sanctum',
        'permission:pricing.calculate'
    ]);

Route::patch(
    '/partner-quotes/{partnerQuote}/accept',
    [PartnerQuoteController::class, 'accept']
);

Route::patch(
    '/partner-quotes/{partnerQuote}',
    [PartnerQuoteController::class, 'update']
);

Route::patch(
    '/commission-distributions/{commissionDistribution}/pay',
    [CommissionDistributionController::class, 'pay']
);

// Industry-specific routes (automotive, ...)
app(\App\Modules\IndustryRegistry::class)->loadRoutes();
