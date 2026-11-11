<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Cookies are sent by the browser automatically, so a malicious site could try to make
 * the victim's browser call this API (CSRF). For cookie-authenticated writes we therefore
 * insist the request really came from our web app.
 */
class RequireTrustedOrigin
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->is('api/*') && $request->attributes->get('auth_via_cookie') && ! $request->isMethodSafe()) {
            $origin = $request->headers->get('Origin') ?: $this->originOf((string) $request->headers->get('Referer'));

            if (! $origin || ! in_array(rtrim($origin, '/'), self::allowedOrigins(), true)) {
                return response()->json(['message' => 'Untrusted origin.'], 403);
            }
        }

        return $next($request);
    }

    /** @return list<string> */
    public static function allowedOrigins(): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn ($o) => rtrim(trim((string) $o), '/'),
            array_merge([config('platform.web_url')], config('cors.allowed_origins', []))
        ), fn ($o) => $o !== '' && $o !== '*')));
    }

    private function originOf(string $url): ?string
    {
        $parts = parse_url($url);

        return isset($parts['scheme'], $parts['host'])
            ? $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '')
            : null;
    }
}
