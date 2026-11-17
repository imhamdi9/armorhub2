<?php

namespace Tests\Feature;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** "Continue with Google / Facebook / Apple": the provider's signed token is checked here, never trusted from the browser. */
class SocialLoginTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private $key;

    private string $slug;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
        $store = $this->customer->company;
        $store->update(['status' => 'active', 'settings' => ['storefront' => ['enabled' => true, 'headline' => 'x', 'subheadline' => 'y', 'primary_color' => '#0a7d5a', 'featured_product_ids' => []]]]);
        $this->slug = $store->slug;

        config(['services.google.client_id' => 'google-client', 'services.facebook.app_id' => 'fb-app', 'services.facebook.app_secret' => 'fb-secret']);
        Cache::flush();

        $this->key = $this->newKey();
        $d = openssl_pkey_get_details($this->key)['rsa'];
        $b = fn (string $v) => rtrim(strtr(base64_encode($v), '+/', '-_'), '=');
        Http::fake(['www.googleapis.com/*' => Http::response(['keys' => [['kid' => 'k1', 'kty' => 'RSA', 'alg' => 'RS256', 'n' => $b($d['n']), 'e' => $b($d['e'])]]])]);
    }

    /** A throw-away RSA key. Windows PHP may need to be told where its openssl.cnf is. */
    private function newKey()
    {
        foreach ([null, getenv('OPENSSL_CONF') ?: null, dirname(PHP_BINARY).'/extras/ssl/openssl.cnf'] as $conf) {
            $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA] + ($conf && is_file($conf) ? ['config' => $conf] : []));
            if ($key) {
                return $key;
            }
        }

        $this->markTestSkipped('OpenSSL cannot generate a key here.');
    }

    private function token(array $claims = [], ?string $kid = 'k1'): string
    {
        $b = fn (string $v) => rtrim(strtr(base64_encode($v), '+/', '-_'), '=');
        $head = $b(json_encode(['alg' => 'RS256', 'kid' => $kid]));
        $body = $b(json_encode($claims + ['iss' => 'https://accounts.google.com', 'aud' => 'google-client', 'exp' => time() + 600, 'sub' => 'g-123', 'email' => 'new.person@example.org', 'email_verified' => true, 'name' => 'New Person']));
        openssl_sign("$head.$body", $sig, $this->key, OPENSSL_ALGO_SHA256);

        return "$head.$body.".$b($sig);
    }

    public function test_only_the_providers_that_are_set_up_are_offered(): void
    {
        $this->getJson('/api/auth/social/providers')->assertOk()->assertJsonPath('providers.google.client_id', 'google-client')->assertJsonMissingPath('providers.apple');
    }

    public function test_a_new_customer_can_sign_up_with_google_in_a_store(): void
    {
        $res = $this->postJson('/api/auth/social', ['provider' => 'google', 'credential' => $this->token(), 'store' => $this->slug])->assertOk();

        $user = User::where('email', 'new.person@example.org')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('customer'));
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame($user->id, SocialAccount::where('provider', 'google')->where('provider_user_id', 'g-123')->value('user_id'));
        $this->assertNotEmpty($res->json('user.id'));
    }

    public function test_the_same_google_account_signs_in_again_and_an_existing_email_is_linked(): void
    {
        $this->postJson('/api/auth/social', ['provider' => 'google', 'credential' => $this->token(), 'store' => $this->slug])->assertOk();
        $this->postJson('/api/auth/social', ['provider' => 'google', 'credential' => $this->token()])->assertOk();
        $this->assertSame(1, User::where('email', 'new.person@example.org')->count());

        // Someone who already has an account with that confirmed email signs in to it, without a store.
        $this->postJson('/api/auth/social', ['provider' => 'google', 'credential' => $this->token(['sub' => 'g-999', 'email' => $this->customer->email])])->assertOk()->assertJsonPath('user.id', $this->customer->id);
        $this->assertSame($this->customer->id, SocialAccount::where('provider_user_id', 'g-999')->value('user_id'));
    }

    public function test_an_unknown_person_without_a_store_is_told_to_sign_up_first(): void
    {
        $this->postJson('/api/auth/social', ['provider' => 'google', 'credential' => $this->token()])->assertUnprocessable()->assertJsonValidationErrors('credential');
        $this->assertSame(0, User::where('email', 'new.person@example.org')->count());
    }

    public function test_forged_expired_or_foreign_tokens_are_refused(): void
    {
        $other = $this->newKey();
        $good = $this->token();
        [$h, $p] = explode('.', $good);
        openssl_sign("$h.$p", $sig, $other, OPENSSL_ALGO_SHA256);
        $forged = "$h.$p.".rtrim(strtr(base64_encode($sig), '+/', '-_'), '=');

        foreach ([$forged, $this->token(['aud' => 'someone-elses-app']), $this->token(['exp' => time() - 10]), $this->token(['iss' => 'https://evil.example']), $this->token([], 'unknown-kid')] as $bad) {
            $this->postJson('/api/auth/social', ['provider' => 'google', 'credential' => $bad, 'store' => $this->slug])->assertUnprocessable();
        }
        $this->assertSame(0, User::where('email', 'new.person@example.org')->count());
    }

    public function test_an_unconfirmed_email_does_not_take_over_an_account(): void
    {
        $this->postJson('/api/auth/social', ['provider' => 'google', 'credential' => $this->token(['email' => $this->customer->email, 'email_verified' => false])])->assertUnprocessable();
        $this->assertSame(0, SocialAccount::count());
    }

    public function test_facebook_is_checked_with_facebook_and_must_belong_to_this_app(): void
    {
        Http::fake([
            'graph.facebook.com/debug_token*' => Http::sequence()->push(['data' => ['is_valid' => true, 'app_id' => 'fb-app']])->push(['data' => ['is_valid' => true, 'app_id' => 'another-app']]),
            'graph.facebook.com/me*' => Http::response(['id' => 'fb-1', 'name' => 'Fay Book', 'email' => 'fay@example.org']),
        ]);

        $this->postJson('/api/auth/social', ['provider' => 'facebook', 'credential' => 'fb-token', 'store' => $this->slug])->assertOk();
        $this->assertNotNull(User::where('email', 'fay@example.org')->first());

        $this->postJson('/api/auth/social', ['provider' => 'facebook', 'credential' => 'fb-token-2', 'store' => $this->slug])->assertUnprocessable();
    }

    public function test_a_provider_that_is_not_set_up_is_refused(): void
    {
        $this->postJson('/api/auth/social', ['provider' => 'apple', 'credential' => 'x', 'store' => $this->slug])->assertUnprocessable();
    }
}
