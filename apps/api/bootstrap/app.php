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

        $middleware->alias([
           'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
           'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
           'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
           'market.scope' => \App\Http\Middleware\ScopeMarketSettings::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions): void {

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

    })
    ->create();