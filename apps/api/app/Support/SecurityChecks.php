<?php

namespace App\Support;

use App\Support\Settings\Settings;

/** A checklist of production-safety settings, shown to admins and runnable from the CLI. */
class SecurityChecks
{
    public function __construct(private Settings $settings)
    {
    }

    /** @return list<array{id: string, status: 'ok'|'warn'|'fail', message: string}> */
    public function run(): array
    {
        $production = app()->isProduction();
        $cors = config('cors.allowed_origins', []);
        $url = (string) config('app.url');

        $checks = [
            $this->check('debug', ! config('app.debug') ? 'ok' : ($production ? 'fail' : 'warn'),
                config('app.debug') ? 'APP_DEBUG is on: errors expose code and configuration. Turn it off in production.' : 'Debug mode is off.'),
            $this->check('https', str_starts_with($url, 'https://') ? 'ok' : ($production ? 'fail' : 'warn'),
                str_starts_with($url, 'https://') ? 'The API is served over HTTPS.' : 'APP_URL is not HTTPS: sessions and passwords would travel in clear text.'),
            $this->check('app_key', config('app.key') ? 'ok' : 'fail',
                config('app.key') ? 'An application key is set.' : 'APP_KEY is missing: encrypted settings and tokens are unsafe.'),
            $this->check('cors', in_array('*', $cors, true) ? 'fail' : 'ok',
                in_array('*', $cors, true) ? 'CORS allows every website.' : 'CORS only allows: '.implode(', ', $cors)),
            $this->check('proxies', env('TRUSTED_PROXIES') ? 'ok' : ($production ? 'warn' : 'ok'),
                env('TRUSTED_PROXIES')
                    ? 'Trusted proxies are configured, so per-IP limits see real visitor addresses.'
                    : ($production
                        ? 'TRUSTED_PROXIES is not set. Behind a reverse proxy every visitor looks like the same IP and rate limits misbehave.'
                        : 'TRUSTED_PROXIES is not set (fine for local use; set it when running behind a reverse proxy).')),
            $this->check('mail', in_array(config('mail.default'), ['log', 'array'], true) ? 'warn' : 'ok',
                in_array(config('mail.default'), ['log', 'array'], true) ? 'Email is not delivered (log driver): password resets, notifications and email verification do not work.' : 'Email delivery is configured.'),
            $this->check('token_expiry', (int) config('sanctum.expiration') > 0 ? 'ok' : 'warn',
                (int) config('sanctum.expiration') > 0 ? 'Sessions expire after '.(int) config('sanctum.expiration').' minutes.' : 'Sessions never expire.'),
            $this->check('pwned', config('platform.check_pwned_passwords') ? 'ok' : ($production ? 'warn' : 'ok'),
                config('platform.check_pwned_passwords') ? 'Breached passwords are rejected.' : 'Breached-password check is off.'),
            $this->check('captcha', $this->settings->get('security.captcha_provider') !== 'none' ? 'ok' : 'warn',
                $this->settings->get('security.captcha_provider') !== 'none' ? 'A bot challenge protects signup.' : 'No bot challenge is enabled. Signup relies on rate limits and the honeypot/timing checks; add Turnstile/hCaptcha for stronger protection.'),
            $this->check('verification', app(\App\Support\Security\EmailPolicy::class)->verificationRequired() ? 'ok' : 'warn',
                app(\App\Support\Security\EmailPolicy::class)->verificationRequired() ? 'Customers must verify their email before ordering.' : 'Email verification is not required, so anyone can order with an address they do not own.'),
            $this->check('payments', $this->paymentsCheck()[0], $this->paymentsCheck()[1]),
            $this->check('shipping', ...$this->shippingCheck()),
        ];

        return $checks;
    }

    /** @return array{0: string, 1: string} */
    private function shippingCheck(): array
    {
        $active = $this->settings->registry()->has('shipping.active_provider') ? $this->settings->get('shipping.active_provider') : 'manual';

        return $active === 'simulated' && app()->isProduction()
            ? ['warn', 'Shipping uses the demo carrier: no real parcels are booked. Choose a real or manual provider before launch.']
            : ['ok', "Shipping uses the '{$active}' provider."];
    }

    /** @return array{0: string, 1: string} */
    private function paymentsCheck(): array
    {
        $provider = $this->settings->registry()->has('payments.active_provider')
            ? $this->settings->get('payments.active_provider')
            : config('platform.payment_gateway');

        if ($provider === 'simulated') {
            return [config('platform.allow_simulated_payments_in_production') || ! app()->isProduction() ? 'warn' : 'ok',
                'Payments use the simulated provider: no money is collected. Choose a real provider before launch.'];
        }

        return ['ok', "Payments use the '{$provider}' provider."];
    }

    /** @return array{id: string, status: string, message: string} */
    private function check(string $id, string $status, string $message): array
    {
        return compact('id', 'status', 'message');
    }
}
