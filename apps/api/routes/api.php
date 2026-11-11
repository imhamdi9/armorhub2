<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\CommissionController;
use App\Http\Controllers\Api\CommissionDashboardController;
use App\Http\Controllers\Api\CommissionDistributionController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\IndustryController;
use App\Http\Controllers\Api\MyItemsController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OrderLifecycleController;
use App\Http\Controllers\Api\PartnerJobController;
use App\Http\Controllers\Api\WarrantyController;
use App\Http\Controllers\Api\PartnerController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\PartnerOrderController;
use App\Http\Controllers\Api\PartnerQuoteController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PricingController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductPriceRuleController;
use App\Http\Controllers\Api\TenantController;
use App\Modules\IndustryRegistry;
use Illuminate\Support\Facades\Route;

// Public
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth');
Route::post('/forgot-password', [PasswordResetController::class, 'forgot'])->middleware('throttle:auth');
Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:auth');

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {

    // Session
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/me/location', [AuthController::class, 'updateLocation']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Industries and shared catalogs
    Route::get('/industries', [IndustryController::class, 'index']);
    Route::get('/catalog/{alias}', [CatalogController::class, 'index']);
    Route::middleware('permission:catalog.manage')->group(function () {
        Route::post('/catalog/{alias}', [CatalogController::class, 'store']);
        Route::put('/catalog/{alias}/{id}', [CatalogController::class, 'update']);
        Route::delete('/catalog/{alias}/{id}', [CatalogController::class, 'destroy']);
    });

    // Customer-owned items (property, ...)
    Route::get('/my-items/{alias}', [MyItemsController::class, 'index']);
    Route::post('/my-items/{alias}', [MyItemsController::class, 'store']);
    Route::delete('/my-items/{alias}/{id}', [MyItemsController::class, 'destroy']);

    // Companies
    Route::get('/companies', [CompanyController::class, 'index'])->middleware('permission:companies.view');
    Route::post('/companies', [CompanyController::class, 'store'])->middleware('permission:companies.create');
    Route::get('/companies/{company}', [CompanyController::class, 'show'])->middleware('permission:companies.view');
    Route::put('/companies/{company}', [CompanyController::class, 'update'])->middleware('permission:companies.update');
    Route::delete('/companies/{company}', [CompanyController::class, 'destroy'])->middleware('permission:companies.delete');

    // Staff
    Route::middleware('permission:users.manage')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);
    });

    // Tenants (platform admin)
    Route::middleware('permission:tenants.manage')->group(function () {
        Route::get('/tenants', [TenantController::class, 'index']);
        Route::post('/tenants', [TenantController::class, 'store']);
        Route::put('/tenants/{tenant}', [TenantController::class, 'update']);
    });

    // Products and pricing
    Route::get('/products', [ProductController::class, 'index'])->middleware('permission:products.view');
    Route::post('/products', [ProductController::class, 'store'])->middleware('permission:products.create');
    Route::get('/products/{product}', [ProductController::class, 'show'])->middleware('permission:products.view');
    Route::put('/products/{product}', [ProductController::class, 'update'])->middleware('permission:products.update');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->middleware('permission:products.delete');
    Route::get('/products/{product}/price-rules', [ProductPriceRuleController::class, 'index'])->middleware('permission:products.view');
    Route::put('/products/{product}/price-rules', [ProductPriceRuleController::class, 'sync'])->middleware('permission:products.update');
    Route::post('/pricing/calculate', [PricingController::class, 'calculate'])->middleware('permission:pricing.calculate');

    // Partners
    Route::get('/partners', [PartnerController::class, 'index'])->middleware('permission:partners.view');
    Route::post('/partners', [PartnerController::class, 'store'])->middleware('permission:partners.create');
    Route::get('/partners/{partner}', [PartnerController::class, 'show'])->middleware('permission:partners.view');
    Route::put('/partners/{partner}', [PartnerController::class, 'update'])->middleware('permission:partners.update');
    Route::delete('/partners/{partner}', [PartnerController::class, 'destroy'])->middleware('permission:partners.delete');

    // Orders, quotes, payments
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::get('/orders/{order}/partner-quotes', [PartnerQuoteController::class, 'index']);
    Route::patch('/partner-quotes/{id}/accept', [PartnerQuoteController::class, 'accept']);
    Route::post('/orders/{id}/cancel', [OrderLifecycleController::class, 'cancel']);
    Route::get('/warranties', [WarrantyController::class, 'index']);
    Route::post('/orders/{order}/payments', [PaymentController::class, 'store'])->middleware('permission:payments.create');

    // Partner side: staff work the jobs assigned to their partner
    Route::middleware('role:partner-manager|technician')->group(function () {
        Route::get('/partner/jobs', [PartnerJobController::class, 'index']);
        Route::post('/orders/{id}/schedule', [OrderLifecycleController::class, 'schedule']);
        Route::post('/orders/{id}/start', [OrderLifecycleController::class, 'start']);
        Route::post('/orders/{id}/complete', [OrderLifecycleController::class, 'complete']);
    });

    // Pricing requests: managers only
    Route::middleware('role:partner-manager')->group(function () {
        Route::get('/partner/orders', [PartnerOrderController::class, 'index']);
        Route::patch('/partner-quotes/{id}', [PartnerOrderController::class, 'submitQuote']);
    });

    // Commissions
    Route::get('/commissions', [CommissionController::class, 'index'])->middleware('permission:commissions.view');
    Route::get('/commissions/{id}', [CommissionController::class, 'show'])->middleware('permission:commissions.view');
    Route::get('/commission-distributions', [CommissionDistributionController::class, 'index'])->middleware('permission:commissions.view');
    Route::patch('/commission-distributions/{commissionDistribution}/pay', [CommissionDistributionController::class, 'pay'])->middleware('permission:commissions.pay');
    Route::get('/dashboard/commissions', [CommissionDashboardController::class, 'commissions'])->middleware('permission:dashboard.view');
    Route::get('/dashboard/earnings', [CommissionDashboardController::class, 'earnings'])->middleware('permission:dashboard.view');
});

// Industry-specific routes (automotive, ...)
app(IndustryRegistry::class)->loadRoutes();
