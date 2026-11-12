<?php

use App\Http\Controllers\Api\Admin\AuditLogController;
use App\Http\Controllers\Api\Admin\OverviewController;
use App\Http\Controllers\Api\Admin\PaymentProviderController;
use App\Http\Controllers\Api\Admin\SecurityController;
use App\Http\Controllers\Api\Admin\SettingsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\CommissionController;
use App\Http\Controllers\Api\CommissionDashboardController;
use App\Http\Controllers\Api\CommissionDistributionController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\IndustryController;
use App\Http\Controllers\Api\MyItemsController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OrderLifecycleController;
use App\Http\Controllers\Api\PartnerJobController;
use App\Http\Controllers\Api\WarrantyController;
use App\Http\Controllers\Api\PartnerController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\PublicStorefrontController;
use App\Http\Controllers\Api\StorefrontController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\PartnerOrderController;
use App\Http\Controllers\Api\PartnerQuoteController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PricingController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductPriceRuleController;
use App\Http\Controllers\Api\PaymentWebhookController;
use App\Http\Controllers\Api\TenantController;
use App\Modules\IndustryRegistry;
use Illuminate\Support\Facades\Route;

// Public
// Public storefront: browse a store and its catalog without an account (read-only, cacheable)
Route::prefix('public')->middleware('throttle:public')->group(function () {
    Route::get('/marketplaces', [PublicStorefrontController::class, 'marketplaces']);
    Route::get('/showcase', [PublicStorefrontController::class, 'showcase']);
    Route::get('/markets', [PublicStorefrontController::class, 'markets']);
    Route::get('/media/{name}', [\App\Http\Controllers\Api\MediaReadController::class, 'show']);
    Route::get('/surfaces/{key}', [\App\Http\Controllers\Api\SurfaceReadController::class, 'publicSurface'])->where('key', '[a-z0-9_.]+');
    Route::get('/storefronts', [PublicStorefrontController::class, 'index']);
    Route::get('/storefronts/{slug}', [PublicStorefrontController::class, 'show']);
    Route::get('/storefronts/{slug}/products', [PublicStorefrontController::class, 'products']);
    Route::get('/storefronts/{slug}/products/{id}', [PublicStorefrontController::class, 'product'])->whereNumber('id');
});

// Buying goods without an account: one order, reached by its secret link
Route::prefix('guest-orders')->group(function () {
    $g = \App\Http\Controllers\Api\GuestOrderController::class;
    Route::post('/', [$g, 'store'])->middleware('throttle:guest-orders');
    Route::get('/{token}', [$g, 'show'])->middleware('throttle:public');
    Route::post('/{token}/pay', [$g, 'pay'])->middleware('throttle:guest-orders');
    Route::post('/{token}/cancel', [$g, 'cancel'])->middleware('throttle:guest-orders');
});

Route::get('/signup-challenge', [AuthController::class, 'challenge'])->middleware('throttle:challenge');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');
Route::post('/partner-applications', [\App\Http\Controllers\Api\PartnerApplicationController::class, 'apply'])->middleware('throttle:register');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['signed', 'throttle:challenge'])->name('verification.verify');
// Called by Stripe, authenticated by its signature
Route::post('/webhooks/shipping/{provider}/{market}', [\App\Http\Controllers\Api\ShippingWebhookController::class, 'handle'])->where('provider', '[a-z0-9_-]+')->whereNumber('market')->middleware('throttle:webhook');
Route::post('/webhooks/shipping/{provider}', [\App\Http\Controllers\Api\ShippingWebhookController::class, 'handle'])->where('provider', '[a-z0-9_-]+')->middleware('throttle:webhook');
Route::post('/webhooks/{provider}/{market}', [PaymentWebhookController::class, 'handle'])->where('provider', '[a-z0-9_-]+')->whereNumber('market')->middleware('throttle:webhook');
Route::post('/webhooks/{provider}', [PaymentWebhookController::class, 'handle'])->where('provider', '[a-z0-9_-]+')->middleware('throttle:webhook');

Route::post('/forgot-password', [PasswordResetController::class, 'forgot'])->middleware('throttle:forgot');
Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:forgot');

Route::middleware(['auth:sanctum', 'throttle:api', 'market.scope'])->group(function () {

    // Session
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/me/location', [AuthController::class, 'updateLocation']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/email/resend', [EmailVerificationController::class, 'resend'])->middleware('throttle:resend');

    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unread']);
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'read']);

    // Industries and shared catalogs
    Route::get('/industries', [IndustryController::class, 'index']);
    Route::get('/catalog/{alias}', [CatalogController::class, 'index']);
    Route::get('/catalog/{alias}/schema', [CatalogController::class, 'schema']);
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
    Route::get('/companies/{company}/storefront', [StorefrontController::class, 'show'])->middleware('permission:companies.update');
    Route::put('/companies/{company}/storefront', [StorefrontController::class, 'update'])->middleware('permission:companies.update');

    // A market manager: the payment, shipping and fee settings of THEIR market only (never the platform-wide ones)
    Route::middleware(['market.scope:own'])->prefix('market')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\MarketSelfController::class, 'show']);
        Route::get('/settings', [SettingsController::class, 'index']);
        Route::put('/settings', [SettingsController::class, 'update']);
        Route::delete('/settings/{key}', [SettingsController::class, 'reset'])->where('key', '[A-Za-z0-9_.]+');
        Route::get('/shipping/providers', [\App\Http\Controllers\Api\Admin\ShippingProviderController::class, 'index']);
        Route::put('/shipping/providers/{key}', [\App\Http\Controllers\Api\Admin\ShippingProviderController::class, 'update']);
        Route::post('/shipping/providers/{key}/test', [\App\Http\Controllers\Api\Admin\ShippingProviderController::class, 'test']);
        Route::post('/shipping/active', [\App\Http\Controllers\Api\Admin\ShippingProviderController::class, 'activate']);
        Route::get('/payments/providers', [PaymentProviderController::class, 'index']);
        Route::put('/payments/providers/{key}', [PaymentProviderController::class, 'update']);
        Route::post('/payments/providers/{key}/test', [PaymentProviderController::class, 'test']);
        Route::post('/payments/active', [PaymentProviderController::class, 'activate']);
        Route::get('/payments', [PaymentProviderController::class, 'payments']);
    });

    // Platform administration (settings, audit log)
    Route::middleware('permission:admin.panel')->prefix('admin')->group(function () {
        Route::get('/markets', [\App\Http\Controllers\Api\Admin\MarketController::class, 'index']);
        Route::get('/countries', [\App\Http\Controllers\Api\Admin\MarketController::class, 'countries']);
        Route::post('/markets', [\App\Http\Controllers\Api\Admin\MarketController::class, 'store']);
        Route::put('/markets/{tenant}', [\App\Http\Controllers\Api\Admin\MarketController::class, 'update'])->whereNumber('tenant');
        $ia = \App\Http\Controllers\Api\Admin\InstallationAdminController::class;
        Route::get('/installation-disputes', [$ia, 'disputes']);
        Route::post('/installation-disputes/{id}/decide', [$ia, 'decide'])->whereNumber('id');
        Route::post('/installation-requests/{id}/refund-paid', [$ia, 'refundPaid'])->whereNumber('id');
        Route::get('/centre-withdrawals', [$ia, 'withdrawals']);
        Route::post('/centre-withdrawals/{id}/pay', [$ia, 'payWithdrawal'])->whereNumber('id');
        Route::post('/centre-withdrawals/{id}/reject', [$ia, 'rejectWithdrawal'])->whereNumber('id');
        Route::get('/installation-violations', [$ia, 'violations']);
        Route::post('/installation-violations/{id}/decide', [$ia, 'decideViolation'])->whereNumber('id');
        Route::get('/overview', OverviewController::class);
        Route::get('/settings', [SettingsController::class, 'index']);
        Route::put('/settings', [SettingsController::class, 'update']);
        Route::delete('/settings/{key}', [SettingsController::class, 'reset'])->where('key', '[A-Za-z0-9_.]+');
        Route::get('/audit-logs', [AuditLogController::class, 'index']);
        Route::get('/shipping/providers', [\App\Http\Controllers\Api\Admin\ShippingProviderController::class, 'index']);
        Route::put('/shipping/providers/{key}', [\App\Http\Controllers\Api\Admin\ShippingProviderController::class, 'update']);
        Route::post('/shipping/providers/{key}/test', [\App\Http\Controllers\Api\Admin\ShippingProviderController::class, 'test']);
        Route::post('/shipping/active', [\App\Http\Controllers\Api\Admin\ShippingProviderController::class, 'activate']);
        Route::get('/payments/providers', [PaymentProviderController::class, 'index']);
        Route::put('/payments/providers/{key}', [PaymentProviderController::class, 'update']);
        Route::post('/payments/providers/{key}/test', [PaymentProviderController::class, 'test']);
        Route::post('/payments/active', [PaymentProviderController::class, 'activate']);
        Route::get('/payments', [PaymentProviderController::class, 'payments']);
        Route::get('/platform/modules', [\App\Http\Controllers\Api\Admin\PlatformController::class, 'modules']);
        Route::put('/platform/tenants/{tenant}/modules/{module}', [\App\Http\Controllers\Api\Admin\PlatformController::class, 'setModule'])->where('module', '[a-z0-9-]+');
        Route::get('/media', [\App\Http\Controllers\Api\Admin\MediaController::class, 'index']);
        Route::post('/media', [\App\Http\Controllers\Api\Admin\MediaController::class, 'store'])->middleware('throttle:30,1');
        Route::get('/roles', [\App\Http\Controllers\Api\Admin\RoleController::class, 'index']);
        Route::post('/roles', [\App\Http\Controllers\Api\Admin\RoleController::class, 'store']);
        Route::put('/roles/{id}', [\App\Http\Controllers\Api\Admin\RoleController::class, 'update'])->whereNumber('id');
        Route::delete('/roles/{id}', [\App\Http\Controllers\Api\Admin\RoleController::class, 'destroy'])->whereNumber('id');
        Route::get('/payouts', [\App\Http\Controllers\Api\Admin\PayoutController::class, 'index']);
        Route::post('/payouts', [\App\Http\Controllers\Api\Admin\PayoutController::class, 'store']);
        Route::post('/payouts/{id}/paid', [\App\Http\Controllers\Api\Admin\PayoutController::class, 'paid'])->whereNumber('id');
        Route::get('/surfaces', [\App\Http\Controllers\Api\Admin\SurfaceController::class, 'index']);
        Route::get('/surfaces/{key}', [\App\Http\Controllers\Api\Admin\SurfaceController::class, 'show'])->where('key', '[a-z0-9_.]+');
        Route::put('/surfaces/{key}', [\App\Http\Controllers\Api\Admin\SurfaceController::class, 'save'])->where('key', '[a-z0-9_.]+');
        Route::post('/surfaces/{key}/publish', [\App\Http\Controllers\Api\Admin\SurfaceController::class, 'publish'])->where('key', '[a-z0-9_.]+');
        Route::get('/surface-publications', [\App\Http\Controllers\Api\Admin\SurfaceController::class, 'publications']);
        Route::post('/surface-publications/{id}/approve', [\App\Http\Controllers\Api\Admin\SurfaceController::class, 'approve'])->whereNumber('id');
        Route::post('/surface-publications/{id}/reject', [\App\Http\Controllers\Api\Admin\SurfaceController::class, 'reject'])->whereNumber('id');
        Route::post('/surface-publications/{id}/cancel', [\App\Http\Controllers\Api\Admin\SurfaceController::class, 'cancel'])->whereNumber('id');
        Route::post('/surfaces/{key}/rollback', [\App\Http\Controllers\Api\Admin\SurfaceController::class, 'rollback'])->where('key', '[a-z0-9_.]+');
        Route::get('/platform/blueprints', [\App\Http\Controllers\Api\Admin\BlueprintController::class, 'index']);
        Route::get('/platform/blueprints/applications', [\App\Http\Controllers\Api\Admin\BlueprintController::class, 'history']);
        Route::post('/platform/blueprints/{id}/apply', [\App\Http\Controllers\Api\Admin\BlueprintController::class, 'apply'])->where('id', '[a-z0-9.-]+');
        Route::get('/platform/workflows', [\App\Http\Controllers\Api\Admin\WorkflowController::class, 'index']);
        Route::put('/platform/tenants/{tenant}/workflows/{key}', [\App\Http\Controllers\Api\Admin\WorkflowController::class, 'update'])->where('key', '[a-z0-9_.]+');
        Route::delete('/platform/tenants/{tenant}/workflows/{key}', [\App\Http\Controllers\Api\Admin\WorkflowController::class, 'reset'])->where('key', '[a-z0-9_.]+');
        Route::get('/platform/events/catalog', [\App\Http\Controllers\Api\Admin\PlatformController::class, 'catalog']);
        Route::get('/platform/events', [\App\Http\Controllers\Api\Admin\PlatformController::class, 'events']);
        Route::get('/platform/events/{eventId}', [\App\Http\Controllers\Api\Admin\PlatformController::class, 'event']);
        Route::post('/platform/events/{eventId}/replay', [\App\Http\Controllers\Api\Admin\PlatformController::class, 'replay']);
        Route::get('/security', [SecurityController::class, 'overview']);
        Route::post('/security/revoke-all-sessions', [SecurityController::class, 'revokeAllSessions']);
    });

    // Staff
    Route::middleware('permission:users.manage')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::post('/users/{id}/revoke-sessions', [UserController::class, 'revokeSessions']);
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
    Route::post('/products/bulk-installation', [ProductController::class, 'bulkInstallation'])->middleware('permission:products.update');
    Route::get('/products/categories', [ProductController::class, 'categories'])->middleware('permission:products.view');
    Route::post('/product-images', [\App\Http\Controllers\Api\ProductImageController::class, 'store'])->middleware(['permission:products.update', 'throttle:30,1']);
    Route::post('/products', [ProductController::class, 'store'])->middleware('permission:products.create');
    Route::get('/products/{product}', [ProductController::class, 'show'])->middleware('permission:products.view');
    Route::put('/products/{product}', [ProductController::class, 'update'])->middleware('permission:products.update');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->middleware('permission:products.delete');
    Route::get('/products/{product}/price-rules', [ProductPriceRuleController::class, 'index'])->middleware('permission:products.view');
    Route::put('/products/{product}/price-rules', [ProductPriceRuleController::class, 'sync'])->middleware('permission:products.update');
    Route::post('/pricing/calculate', [PricingController::class, 'calculate'])->middleware('permission:pricing.calculate');

    // Partner applications: reviewed by platform admins and the store's owners
    Route::get('/partner-applications', [\App\Http\Controllers\Api\PartnerApplicationController::class, 'index']);
    Route::get('/partner-applications/{id}/documents/{documentId}', [\App\Http\Controllers\Api\PartnerApplicationController::class, 'document'])->whereNumber(['id', 'documentId']);
    Route::post('/partner-applications/{id}/approve', [\App\Http\Controllers\Api\PartnerApplicationController::class, 'approve'])->whereNumber('id');
    Route::post('/partner-applications/{id}/reject', [\App\Http\Controllers\Api\PartnerApplicationController::class, 'reject'])->whereNumber('id');

    // Partners
    Route::get('/partners', [PartnerController::class, 'index'])->middleware('permission:partners.view');
    Route::post('/partners', [PartnerController::class, 'store'])->middleware('permission:partners.create');
    Route::get('/partners/{partner}', [PartnerController::class, 'show'])->middleware('permission:partners.view');
    Route::put('/partners/{partner}', [PartnerController::class, 'update'])->middleware('permission:partners.update');
    Route::delete('/partners/{partner}', [PartnerController::class, 'destroy'])->middleware('permission:partners.delete');

    // Orders, quotes, payments
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store'])->middleware('throttle:orders');
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::get('/orders/{order}/partner-quotes', [PartnerQuoteController::class, 'index']);
    Route::patch('/partner-quotes/{id}/accept', [PartnerQuoteController::class, 'accept']);
    Route::post('/orders/{id}/cancel', [OrderLifecycleController::class, 'cancel']);
    Route::get('/warranties', [WarrantyController::class, 'index']);
    Route::post('/orders/{order}/payments', [PaymentController::class, 'store'])->middleware('permission:payments.create');

    // Installation help: customers ask for it; centres answer without ever seeing who asks
    Route::get('/installation-requests', [\App\Http\Controllers\Api\InstallationRequestController::class, 'index']);
    Route::post('/installation-requests', [\App\Http\Controllers\Api\InstallationRequestController::class, 'store'])->middleware('throttle:10,60');
    Route::get('/installation-requests/{id}', [\App\Http\Controllers\Api\InstallationRequestController::class, 'show'])->whereNumber('id');
    Route::post('/installation-requests/{id}/cancel', [\App\Http\Controllers\Api\InstallationRequestController::class, 'cancel'])->whereNumber('id');
    Route::post('/installation-requests/{id}/accept', [\App\Http\Controllers\Api\InstallationFlowController::class, 'accept'])->whereNumber('id');
    Route::post('/installation-requests/{id}/pay', [\App\Http\Controllers\Api\InstallationFlowController::class, 'pay'])->whereNumber('id')->middleware('throttle:20,1');
    Route::post('/installation-requests/{id}/confirm', [\App\Http\Controllers\Api\InstallationFlowController::class, 'confirm'])->whereNumber('id');
    Route::post('/installation-requests/{id}/review', [\App\Http\Controllers\Api\InstallationFlowController::class, 'review'])->whereNumber('id');
    Route::post('/installation-requests/{id}/disputes', [\App\Http\Controllers\Api\InstallationFlowController::class, 'dispute'])->whereNumber('id');
    Route::post('/installation-requests/{id}/evidence', [\App\Http\Controllers\Api\InstallationFlowController::class, 'evidence'])->whereNumber('id')->middleware('throttle:30,1');
    Route::post('/installation-requests/{id}/report-contact', [\App\Http\Controllers\Api\InstallationFlowController::class, 'reportContact'])->whereNumber('id')->middleware('throttle:10,1');
    Route::get('/loyalty', [\App\Http\Controllers\Api\InstallationFlowController::class, 'loyalty']);
    Route::get('/installation-files/{id}', [\App\Http\Controllers\Api\InstallationFlowController::class, 'file'])->whereNumber('id');
    Route::middleware('role:partner-manager|technician')->prefix('partner/installation-jobs')->group(function () {
        $c = \App\Http\Controllers\Api\CentreJobController::class;
        Route::get('/', [$c, 'index']);
        Route::get('/{id}', [$c, 'show'])->whereNumber('id');
        Route::post('/{id}/start', [$c, 'start'])->whereNumber('id');
        Route::post('/{id}/proof', [$c, 'proof'])->whereNumber('id')->middleware('throttle:30,1');
        Route::post('/{id}/disputes', [$c, 'dispute'])->whereNumber('id');
        Route::post('/{id}/evidence', [$c, 'evidence'])->whereNumber('id')->middleware('throttle:30,1');
    });
    Route::middleware('role:partner-manager|company-owner')->group(function () {
        Route::get('/partner/wallet', [\App\Http\Controllers\Api\CentreJobController::class, 'wallet']);
        Route::post('/partner/wallet/withdrawals', [\App\Http\Controllers\Api\CentreJobController::class, 'withdraw'])->middleware('throttle:10,1');
    });
    Route::middleware('role:partner-manager')->group(function () {
        Route::get('/partner/installation-requests', [\App\Http\Controllers\Api\PartnerInstallationController::class, 'index']);
        Route::post('/partner/installation-requests/{id}/offer', [\App\Http\Controllers\Api\PartnerInstallationController::class, 'offer'])->whereNumber('id')->middleware('throttle:60,1');
    });

    // Shipping: staff book and track parcels; customers see tracking on their own orders
    Route::get('/shipping-providers', [\App\Http\Controllers\Api\ShipmentController::class, 'providers']);
    Route::get('/orders/{id}/shipments', [\App\Http\Controllers\Api\ShipmentController::class, 'index'])->whereNumber('id');
    Route::post('/orders/{id}/shipping-rates', [\App\Http\Controllers\Api\ShipmentController::class, 'rates'])->whereNumber('id')->middleware('throttle:30,1');
    Route::post('/orders/{id}/shipments', [\App\Http\Controllers\Api\ShipmentController::class, 'store'])->whereNumber('id')->middleware('throttle:30,1');
    Route::post('/shipments/{id}/refresh', [\App\Http\Controllers\Api\ShipmentController::class, 'refresh'])->whereNumber('id')->middleware('throttle:30,1');
    Route::post('/shipments/{id}/status', [\App\Http\Controllers\Api\ShipmentController::class, 'status'])->whereNumber('id');

    // Published content of non-public editable surfaces (e.g. the dashboard menu names)
    Route::get('/surfaces/{key}', [\App\Http\Controllers\Api\SurfaceReadController::class, 'signedIn'])->where('key', '[a-z0-9_.]+');

    // Sellers: the orders for their own products, and their earnings
    Route::get('/seller/earnings', [\App\Http\Controllers\Api\SellerEarningsController::class, 'show']);
    Route::get('/seller/orders', [\App\Http\Controllers\Api\SellerOrderController::class, 'index']);
    Route::get('/seller/orders/{id}', [\App\Http\Controllers\Api\SellerOrderController::class, 'show'])->whereNumber('id');

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
