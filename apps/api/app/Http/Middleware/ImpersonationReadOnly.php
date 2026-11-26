<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;

/** While an administrator is "viewing as" someone, the session can look but never change anything (except leaving). */
class ImpersonationReadOnly
{
    public const TOKEN_PREFIX = 'impersonation:';

    /** The name of the token this person is signed in with ('' for a test or transient session). */
    public static function tokenName(?User $user): string
    {
        return (string) ($user?->currentAccessToken()?->name ?? '');
    }

    /** The administrator behind this session when it is a "view as" one, else null. */
    public static function viewedBy(?User $user): ?int
    {
        $name = self::tokenName($user);

        return str_starts_with($name, self::TOKEN_PREFIX) ? (int) substr($name, strlen(self::TOKEN_PREFIX)) : null;
    }

    public function handle(Request $request, Closure $next)
    {
        if (self::viewedBy($request->user()) !== null && ! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true) && ! $request->is('api/impersonation/exit')) {
            abort(403, 'You are viewing this account as an administrator: it is read-only.');
        }

        return $next($request);
    }
}
