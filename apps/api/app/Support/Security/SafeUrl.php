<?php

namespace App\Support\Security;

/**
 * SSRF guard for URLs an administrator configures and the server then calls. Without it, a
 * compromised or careless admin setting could make the server probe internal services or the
 * cloud metadata endpoint (169.254.169.254).
 */
class SafeUrl
{
    /**
     * Curl options that connect to the very address that was validated. Without this the HTTP
     * client would resolve the name again, and a hostile DNS server could answer with a public
     * address for the check and an internal one for the connection (DNS rebinding).
     *
     * @return array<int, mixed>
     */
    public static function curlOptions(string $url): array
    {
        $parts = parse_url($url);
        $host = trim($parts['host'] ?? '', '[]');

        if ($host === '' || filter_var($host, FILTER_VALIDATE_IP) || config('platform.allow_insecure_provider_urls')) {
            return [];
        }

        $ip = (gethostbynamel($host) ?: [])[0] ?? null;
        $port = $parts['port'] ?? (($parts['scheme'] ?? 'https') === 'http' ? 80 : 443);

        return $ip ? [CURLOPT_RESOLVE => ["{$host}:{$port}:{$ip}"]] : [];
    }

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
