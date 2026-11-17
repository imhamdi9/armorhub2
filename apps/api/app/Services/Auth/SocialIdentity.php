<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * Checks what the browser got from Google, Facebook or Apple and turns it into one verified identity.
 * Nothing the browser says is trusted: Google and Apple send a signed token that is checked against the provider's published keys,
 * Facebook's access token is checked by asking Facebook who it belongs to.
 */
class SocialIdentity
{
    public const PROVIDERS = ['google', 'facebook', 'apple'];

    private const ISSUERS = ['google' => ['https://accounts.google.com', 'accounts.google.com'], 'apple' => ['https://appleid.apple.com']];

    private const JWKS = ['google' => 'https://www.googleapis.com/oauth2/v3/certs', 'apple' => 'https://appleid.apple.com/auth/keys'];

    /** Which providers are set up, with the public id each button needs. @return array<string, array{client_id: string}> */
    public function enabled(): array
    {
        $out = [];
        foreach (['google' => 'services.google.client_id', 'facebook' => 'services.facebook.app_id', 'apple' => 'services.apple.client_id'] as $provider => $key) {
            if ($id = config($key)) {
                $out[$provider] = ['client_id' => (string) $id];
            }
        }

        return $out;
    }

    /** @return array{id: string, email: ?string, email_verified: bool, name: ?string} */
    public function verify(string $provider, string $credential): array
    {
        if (! isset($this->enabled()[$provider])) {
            throw ValidationException::withMessages(['provider' => ['This sign-in method is not available.']]);
        }

        try {
            return $provider === 'facebook' ? $this->facebook($credential) : $this->jwt($provider, $credential);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);
            throw ValidationException::withMessages(['credential' => ['We could not verify your sign-in. Please try again.']]);
        }
    }

    private function facebook(string $token): array
    {
        $appId = config('services.facebook.app_id');
        $secret = config('services.facebook.app_secret');

        // The token must have been issued to this app, not to some other app the same person uses.
        $debug = Http::timeout(8)->get('https://graph.facebook.com/debug_token', ['input_token' => $token, 'access_token' => $appId.'|'.$secret])->json('data');
        if (! is_array($debug) || ($debug['is_valid'] ?? false) !== true || (string) ($debug['app_id'] ?? '') !== (string) $appId) {
            throw ValidationException::withMessages(['credential' => ['We could not verify your sign-in. Please try again.']]);
        }

        $me = Http::timeout(8)->get('https://graph.facebook.com/me', ['fields' => 'id,name,email', 'access_token' => $token])->json();
        if (empty($me['id'])) {
            throw ValidationException::withMessages(['credential' => ['We could not verify your sign-in. Please try again.']]);
        }

        // Facebook only returns an email it has confirmed.
        return ['id' => (string) $me['id'], 'email' => $me['email'] ?? null, 'email_verified' => ! empty($me['email']), 'name' => $me['name'] ?? null];
    }

    private function jwt(string $provider, string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new \InvalidArgumentException('not a token');
        }

        [$h, $p, $s] = $parts;
        $header = json_decode($this->b64($h), true);
        $claims = json_decode($this->b64($p), true);

        if (! is_array($header) || ! is_array($claims) || ($header['alg'] ?? '') !== 'RS256') {
            throw new \InvalidArgumentException('bad token');
        }

        $key = collect($this->keys($provider))->firstWhere('kid', $header['kid'] ?? null);
        if (! $key || ! ($pem = $this->pem($key)) || openssl_verify($h.'.'.$p, $this->b64($s), $pem, OPENSSL_ALGO_SHA256) !== 1) {
            throw new \InvalidArgumentException('bad signature');
        }

        $audience = config("services.{$provider}.client_id");
        if (! in_array($claims['iss'] ?? '', self::ISSUERS[$provider], true) || ! in_array($audience, (array) ($claims['aud'] ?? []), true) || ($claims['exp'] ?? 0) < time()) {
            throw new \InvalidArgumentException('wrong issuer, audience or expired');
        }

        $verified = filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return ['id' => (string) $claims['sub'], 'email' => $claims['email'] ?? null, 'email_verified' => $verified && ! empty($claims['email']), 'name' => $claims['name'] ?? null];
    }

    private function keys(string $provider): array
    {
        return Cache::remember("social.jwks.{$provider}", 3600, fn () => Http::timeout(8)->get(self::JWKS[$provider])->throw()->json('keys') ?? []);
    }

    private function b64(string $v): string
    {
        return base64_decode(strtr($v, '-_', '+/').str_repeat('=', (4 - strlen($v) % 4) % 4), true) ?: '';
    }

    /** An RSA public key (modulus and exponent from the provider's key list) as the PEM that OpenSSL reads. */
    private function pem(array $jwk): ?string
    {
        if (empty($jwk['n']) || empty($jwk['e'])) {
            return null;
        }

        $int = function (string $bin): string {
            $bin = ltrim($bin, "\0");
            if ($bin === '' || ord($bin[0]) > 0x7f) {
                $bin = "\0".$bin;
            }

            return "\x02".$this->len(strlen($bin)).$bin;
        };
        $seq = fn (string $v) => "\x30".$this->len(strlen($v)).$v;

        $rsa = $seq($int($this->b64($jwk['n'])).$int($this->b64($jwk['e'])));
        $algo = hex2bin('300d06092a864886f70d0101010500');
        $bits = "\x03".$this->len(strlen($rsa) + 1)."\0".$rsa;
        $der = $seq($algo.$bits);

        return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END PUBLIC KEY-----\n";
    }

    private function len(int $n): string
    {
        if ($n < 128) {
            return chr($n);
        }
        $bytes = ltrim(pack('N', $n), "\0");

        return chr(0x80 | strlen($bytes)).$bytes;
    }
}
