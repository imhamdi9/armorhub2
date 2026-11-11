<?php

namespace App\Support\Settings;

/**
 * The catalogue of settings. Core definitions live in `core()`; modules such as payment
 * providers add their own at boot via `register()`.
 */
class SettingsRegistry
{
    /** @var array<string, Definition> */
    private array $definitions = [];

    public function __construct()
    {
        foreach ($this->core() as $definition) {
            $this->register($definition);
        }
    }

    public function register(Definition $definition): void
    {
        $this->definitions[$definition->key] = $definition;
    }

    public function has(string $key): bool
    {
        return isset($this->definitions[$key]);
    }

    public function get(string $key): Definition
    {
        return $this->definitions[$key] ?? throw new \InvalidArgumentException("Unknown setting [{$key}].");
    }

    /** @return array<string, Definition> */
    public function all(): array
    {
        return $this->definitions;
    }

    /** @return list<Definition> */
    private function core(): array
    {
        return [
            // ---- Platform -------------------------------------------------
            new Definition('platform.default_market', 'platform', 'Default market (country)', 'select', fn () => config('markets.default'),
                'Sets currency, VAT and phone rules for tenants that have not chosen a country. Leave empty to use the single currency below.',
                options: collect(config('markets.markets'))->mapWithKeys(fn ($m, $code) => [$code => $m['name']['en']])->all()),
            new Definition('platform.currency', 'platform', 'Currency (ISO code)', 'string', fn () => config('platform.currency'),
                'Three-letter code such as USD or SAR. Applies to new orders.', rules: ['size:3', 'alpha']),
            new Definition('platform.commission_percentage', 'platform', 'Commission (% of each order)', 'float',
                fn () => config('platform.commission_percentage'), 'Taken from the order total when it is paid.', rules: ['min:0', 'max:100']),
            new Definition('platform.commission_split.platform', 'platform', 'Commission share: platform', 'float',
                fn () => config('platform.commission_split.platform'), 'The three shares must add up to 1 (e.g. 0.35).', rules: ['min:0', 'max:1']),
            new Definition('platform.commission_split.company', 'platform', 'Commission share: partner company', 'float',
                fn () => config('platform.commission_split.company'), rules: ['min:0', 'max:1']),
            new Definition('platform.commission_split.partner', 'platform', 'Commission share: partner', 'float',
                fn () => config('platform.commission_split.partner'), rules: ['min:0', 'max:1']),
            new Definition('platform.registration_open', 'platform', 'Customer signup is open', 'bool', true,
                'Turn off to stop new accounts being created (e.g. during an attack).'),
            new Definition('platform.partner_signup_open', 'platform', 'Partner applications are open', 'bool', true,
                'Turn off to stop new partner applications (e.g. during an attack).'),
            new Definition('platform.seller_signup_open', 'platform', 'Seller applications are open', 'bool', true,
                'Turn off to stop new seller applications.'),
            new Definition('platform.orders_enabled', 'platform', 'New orders are accepted', 'bool', true,
                'Turn off to pause ordering; existing orders continue normally.'),

            // ---- Security -------------------------------------------------
            new Definition('security.require_email_verification', 'security', 'Require verified email to order', 'select', 'auto',
                '"Automatic" requires it only when real email delivery (SMTP) is configured.',
                ['auto' => 'Automatic', 'yes' => 'Always', 'no' => 'Never']),
            new Definition('security.block_disposable_emails', 'security', 'Block disposable email addresses', 'bool', true,
                'Rejects signups from well-known throwaway email services.'),
            new Definition('security.blocked_email_domains', 'security', 'Extra blocked email domains', 'text', '',
                'One domain per line or comma-separated, e.g. spam.example'),
            new Definition('security.signups_per_ip_per_hour', 'security', 'Signups allowed per IP per hour', 'int', 5,
                rules: ['min:1', 'max:1000']),
            new Definition('security.max_open_orders', 'security', 'Max unpaid orders per customer', 'int', 5,
                'Stops one account flooding partners with quote requests.', rules: ['min:1', 'max:100']),
            new Definition('security.min_signup_seconds', 'security', 'Minimum seconds to fill the signup form', 'int', 3,
                'Bots submit instantly; humans do not.', rules: ['min:0', 'max:60']),
            new Definition('security.max_active_tokens', 'security', 'Max signed-in devices per account', 'int', 10,
                'Oldest sessions are signed out beyond this.', rules: ['min:1', 'max:100']),
            new Definition('security.captcha_provider', 'security', 'Bot challenge on signup/login', 'select', 'none',
                'Cloudflare Turnstile, hCaptcha and reCAPTCHA are supported. Keys below.',
                ['none' => 'Off', 'turnstile' => 'Cloudflare Turnstile', 'hcaptcha' => 'hCaptcha', 'recaptcha' => 'Google reCAPTCHA']),
            new Definition('security.captcha_site_key', 'security', 'Bot challenge site key (public)', 'string', ''),
            new Definition('security.captcha_secret', 'security', 'Bot challenge secret key', 'secret', null),
        ];
    }
}
