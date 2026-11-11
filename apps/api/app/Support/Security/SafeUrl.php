<?php

namespace App\Support\Security;

/**
 * SSRF guard for URLs an administrator configures and the server then calls. Without it, a
 * compromised or careless admin setting could make the server probe internal services or the
 * cloud metadata endpoint (169.254.169.254).
 */
class SafeUrl
{
    /** Why the URL may not be called, or null when it is fine. */
    public static function problem(string $url): ?string
    {
        $parts = parse_url($url);

        if (! $parts || ! isset($parts['host'])) {
            return 'Enter a full URL.';
        }

        if (($parts['scheme'] ?? '') !== 'https' && ! config('platform.allow_insecure_provider_urls')) {
            return 'The URL must start with https://.';
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return 'The URL must not contain a username or password.';
        }

        if (config('platform.allow_insecure_provider_urls')) {
            return null; // local development and tests only
        }

        $host = trim($parts['host'], '[]');
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);

        if ($ips === []) {
            return 'That address does not resolve.';
        }

        foreach ($ips as $ip) {
            // Private ranges, loopback, link-local (incl. cloud metadata) and reserved addresses are off limits.
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return 'That address points to a private or internal network.';
            }
        }

        return null;
    }
}
