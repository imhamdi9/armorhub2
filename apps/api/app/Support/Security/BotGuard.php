<?php

namespace App\Support\Security;

use App\Support\Settings\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Cheap checks that separate people from scripts: a hidden honeypot field, a signed
 * "form was open for a while" token, and an optional captcha (Turnstile/hCaptcha/reCAPTCHA).
 */
class BotGuard
{
    private const TOKEN_TTL = 3600;

    private const VERIFY_URLS = [
        'turnstile' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
        'hcaptcha' => 'https://hcaptcha.com/siteverify',
        'recaptcha' => 'https://www.google.com/recaptcha/api/siteverify',
    ];

    public function __construct(private Settings $settings)
    {
    }

    /** What the signup form needs to load: a timing token and the captcha site key. */
    public function challenge(Request $request): array
    {
        $provider = $this->settings->get('security.captcha_provider');

        return [
            'token' => $this->issueToken($request),
            'captcha' => $provider === 'none' ? null : [
                'provider' => $provider,
                'site_key' => $this->settings->get('security.captcha_site_key'),
            ],
        ];
    }

    /** True when the submission looks human. Never says which check failed. */
    public function passes(Request $request, bool $needsCaptcha = true): bool
    {
        // Honeypot: a field real users never see or fill.
        if ($request->filled('website')) {
            return false;
        }

        if (! $this->validToken((string) $request->input('signup_token'), $request)) {
            return false;
        }

        return ! $needsCaptcha || $this->captchaPasses($request);
    }

    public function issueToken(Request $request): string
    {
        $issued = now()->timestamp;

        return $issued.'.'.$this->sign($issued, $request);
    }

    private function validToken(string $token, Request $request): bool
    {
        [$issued, $signature] = array_pad(explode('.', $token, 2), 2, '');

        if (! ctype_digit($issued) || ! hash_equals($this->sign((int) $issued, $request), $signature)) {
            return false;
        }

        $age = now()->timestamp - (int) $issued;

        if ($age < (int) $this->settings->get('security.min_signup_seconds') || $age > self::TOKEN_TTL) {
            return false;
        }

        // Single use: one fetched token cannot power a whole script.
        return Cache::add('signup-token:'.hash('sha256', $token), 1, self::TOKEN_TTL);
    }

    private function sign(int $issued, Request $request): string
    {
        return hash_hmac('sha256', $issued.'|'.$request->ip(), (string) config('app.key'));
    }

    private function captchaPasses(Request $request): bool
    {
        $provider = $this->settings->get('security.captcha_provider');

        if ($provider === 'none' || ! isset(self::VERIFY_URLS[$provider])) {
            return true;
        }

        $secret = $this->settings->get('security.captcha_secret');
        $response = (string) $request->input('captcha_token');

        // Misconfigured or no answer: fail closed.
        if (! $secret || $response === '') {
            return false;
        }

        try {
            $result = Http::asForm()->timeout(8)->post(self::VERIFY_URLS[$provider], [
                'secret' => $secret,
                'response' => $response,
                'remoteip' => $request->ip(),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return false;
        }

        return $result->ok() && $result->json('success') === true;
    }
}
