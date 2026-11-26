<?php

namespace App\Support\Security;

use App\Support\Settings\Settings;

/** Rules about which email addresses may create accounts. */
class EmailPolicy
{
    public function __construct(private Settings $settings)
    {
    }

    /**
     * The one real mailbox behind an address, so `a+1@x.com`, `a+2@x.com` and (for Gmail)
     * `a.b@gmail.com` / `ab@gmail.com` cannot be used to farm "different" accounts.
     */
    public static function canonical(string $email): string
    {
        $email = mb_strtolower(trim($email));

        if (! str_contains($email, '@')) {
            return $email;
        }

        [$local, $domain] = explode('@', $email, 2);
        $local = explode('+', $local, 2)[0];

        if (in_array($domain, ['gmail.com', 'googlemail.com'], true)) {
            $local = str_replace('.', '', $local);
            $domain = 'gmail.com';
        }

        return $local.'@'.$domain;
    }

    public static function domain(string $email): string
    {
        return mb_strtolower(substr(strrchr(trim($email), '@') ?: '', 1));
    }

    /** True when this domain may not be used for signup. */
    public function isBlocked(string $email): bool
    {
        $domain = self::domain($email);

        if ($domain === '') {
            return true;
        }

        $blocked = collect(preg_split('/[\s,;]+/', mb_strtolower((string) $this->settings->get('security.blocked_email_domains')), -1, PREG_SPLIT_NO_EMPTY));

        if ($this->settings->bool('security.block_disposable_emails')) {
            $blocked = $blocked->merge(config('disposable_domains'));
        }

        // Also blocks subdomains of a blocked domain (x.mailinator.com).
        return $blocked->contains(fn (string $b) => $domain === $b || str_ends_with($domain, '.'.$b));
    }

    /** Whether customers must verify their email before ordering. */
    public function verificationRequired(): bool
    {
        return match ($this->settings->get('security.require_email_verification')) {
            'yes' => true,
            'no' => false,
            // Verification is only possible when mail is really delivered.
            default => ! in_array(config('mail.default'), ['log', 'array'], true),
        };
    }
}
