<?php

namespace App\Support\Security;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Browser sessions live in an HttpOnly cookie so a script injected into the page (XSS)
 * can never read the token. Other clients (mobile apps, scripts) keep using Bearer tokens.
 */
class AuthCookie
{
    public const NAME = 'armorhub_token';

    /** Browsers announce themselves; everything else gets the token in the response body. */
    public static function wanted(Request $request): bool
    {
        return $request->header('X-Client') === 'web';
    }

    public static function make(Request $request, string $token): Cookie
    {
        return Cookie::create(
            self::NAME,
            $token,
            now()->addMinutes((int) config('sanctum.expiration'))->getTimestamp(),
            '/',
            config('platform.auth_cookie_domain') ?: null,
            $request->isSecure() || app()->isProduction(),
            true,   // HttpOnly: invisible to JavaScript
            false,
            Cookie::SAMESITE_LAX
        );
    }

    public static function forget(Request $request): Cookie
    {
        return Cookie::create(self::NAME, '', 1, '/', config('platform.auth_cookie_domain') ?: null,
            $request->isSecure() || app()->isProduction(), true, false, Cookie::SAMESITE_LAX);
    }
}
