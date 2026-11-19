<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // The mobile apps call a versioned address (/api/v1/...) that never changes under them: the same routes, kept stable for old app versions.
        then: function () {
            \Illuminate\Support\Facades\Route::middleware('api')->prefix('api/v1')->group(base_path('routes/api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {

        // Behind a reverse proxy (Caddy, Render...) the real client IP comes from X-Forwarded-For.
        // Without this every visitor looks like the proxy and per-IP limits protect nobody.
        if ($proxies = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        // Global (not in the "api" group): Laravel runs `Authenticate` ahead of unlisted group
        // middleware, so the cookie must be turned into a token before routing starts.
        // Each of these only acts on /api/* requests.
        $middleware->prepend([
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\AuthCookieToBearer::class,
            \App\Http\Middleware\RequireTrustedOrigin::class,
        ]);

        // This app has no server-side login page (sign-in lives in the web app), so a guest must get a plain 401
        // instead of a redirect to an undefined "login" route, which used to end as a 500 when no JSON Accept header was sent.
        $middleware->redirectGuestsTo(fn () => null);

        // The API answers in the language of the app that asks (and remembers each person's language for the e-mails written about them).
        $middleware->append(\App\Http\Middleware\SetLocale::class);

        $middleware->alias([
           'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
           'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
           'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
           'market.scope' => \App\Http\Middleware\ScopeMarketSettings::class,
           'idempotent' => \App\Http\Middleware\Idempotent::class,
           'impersonation.readonly' => \App\Http\Middleware\ImpersonationReadOnly::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions): void {

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

    })
    ->create();