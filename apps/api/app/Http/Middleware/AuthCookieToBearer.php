<?php

namespace App\Http\Middleware;

use App\Support\Security\AuthCookie;
use Closure;
use Illuminate\Http\Request;

/** Lets the browser session cookie authenticate exactly like a Bearer token. */
class AuthCookieToBearer
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->is('api/*') && ! $request->headers->has('Authorization') && ($token = $request->cookies->get(AuthCookie::NAME))) {
            $request->headers->set('Authorization', 'Bearer '.$token);
            $request->attributes->set('auth_via_cookie', true);
        }

        return $next($request);
    }
}
