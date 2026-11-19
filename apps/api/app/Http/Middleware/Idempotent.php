<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Paying twice by accident (a double tap, a retry after a dropped connection) must charge once. A client that sends an `Idempotency-Key` header
 * gets the same answer to the same key and request, without the work being done again; a second request with the key while the first is still
 * running is refused. Failed answers (5xx) are not kept, so a retry can try again. Without the header nothing changes.
 */
class Idempotent
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = (string) $request->header('Idempotency-Key', '');
        if ($key === '') {
            return $next($request);
        }
        if (strlen($key) > 120 || ! preg_match('/^[A-Za-z0-9._:-]+$/', $key)) {
            return response()->json(['message' => 'The Idempotency-Key must be 1 to 120 letters, numbers or . _ : -'], 422);
        }

        $scope = 'idem:'.sha1(($request->user()?->id ?? 'guest').'|'.$request->method().'|'.$request->path().'|'.$key);
        $fingerprint = sha1($request->getContent());

        if ($saved = Cache::get($scope)) {
            if ($saved['fingerprint'] !== $fingerprint) {
                return response()->json(['message' => 'This Idempotency-Key was already used with a different request.'], 422);
            }

            return response($saved['body'], $saved['status'], ['Content-Type' => $saved['type'], 'Idempotent-Replayed' => 'true']);
        }

        if (! Cache::add($scope.':lock', 1, 60)) {
            return response()->json(['message' => 'The same request is still being processed.'], 409);
        }

        try {
            $response = $next($request);
            if ($response->getStatusCode() < 500) {
                Cache::put($scope, ['fingerprint' => $fingerprint, 'status' => $response->getStatusCode(), 'body' => $response->getContent(), 'type' => $response->headers->get('Content-Type', 'application/json')], now()->addDay());
            }

            return $response;
        } finally {
            Cache::forget($scope.':lock');
        }
    }
}
