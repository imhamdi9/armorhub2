<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Symfony\Component\HttpFoundation\Response;

/**
 * The API answers in the language of the app that asks (Accept-Language: ar or en). Messages are written once, in English, where the problem is found;
 * here the answer's "message" and field errors are translated from lang/ar.json when that language is Arabic. The person's language is also remembered,
 * so e-mails and notices written while someone else acts are in their own language.
 */
class SetLocale
{
    public const LOCALES = ['en', 'ar'];

    public static function fromHeader(?string $header): string
    {
        $first = strtolower(trim(explode(',', (string) $header)[0] ?? ''));

        return in_array(substr($first, 0, 2), self::LOCALES, true) ? substr($first, 0, 2) : 'en';
    }

    public function handle(Request $request, Closure $next): Response
    {
        $locale = self::fromHeader($request->header('Accept-Language'));
        app()->setLocale($locale);

        $response = $next($request);

        if ($locale === 'ar' && $response instanceof JsonResponse && $response->getStatusCode() >= 400) {
            $data = $response->getData(true);
            if (is_array($data)) {
                $response->setData($this->translate($data));
            }
        }

        return $response;
    }

    /** Remembers the language of a signed-in person, after the response has gone out. */
    public function terminate(Request $request, Response $response): void
    {
        $user = $request->user();
        if (! $user || ! $request->hasHeader('Accept-Language')) {
            return;
        }
        $locale = self::fromHeader($request->header('Accept-Language'));
        if ($user->locale !== $locale) {
            $user->forceFill(['locale' => $locale])->saveQuietly();
        }
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function translate(array $data): array
    {
        foreach (['message'] as $key) {
            if (isset($data[$key]) && is_string($data[$key])) {
                $data[$key] = $this->one($data[$key]);
            }
        }
        if (isset($data['errors']) && is_array($data['errors'])) {
            foreach ($data['errors'] as $field => $messages) {
                $data['errors'][$field] = array_map(fn ($m) => is_string($m) ? $this->one($m) : $m, (array) $messages);
            }
        }

        return $data;
    }

    private function one(string $message): string
    {
        return Lang::has($message, 'ar') ? (string) Lang::get($message, [], 'ar') : $message;
    }
}
