<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Defensive headers on every API response. */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (! $request->is('api/*')) {
            return $response;
        }

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Permissions-Policy', 'geolocation=(), camera=(), microphone=(), payment=()');
        // The API only ever returns JSON: nothing in a response may load or run anything.
        $response->headers->set('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");
        // Responses are per-user; never let a shared cache keep them.
        if (! str_contains((string) $response->headers->get('Cache-Control'), 'public')) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        $response->headers->remove('X-Powered-By');

        return $response;
    }
}
