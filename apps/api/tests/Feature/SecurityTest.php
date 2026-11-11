<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Industry;
use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Settings\Settings;
use Database\Seeders\IndustrySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** Abuse resistance: fake-account floods, bots, credential attacks, session theft, data isolation. */
class SecurityTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
        RateLimiter::clear('x');
    }

    private function settings(): Settings
    {
        return app(Settings::class);
    }

    /** A signup attempt from a given IP, with a valid human-looking token. */
    private function signup(array $over = [], string $ip = '10.0.0.1', int $waitSeconds = 5)
    {
        $server = ['REMOTE_ADDR' => $ip];
        $challenge = $this->withServerVariables($server)->getJson('/api/signup-challenge')->assertOk()->json();
        $this->travel($waitSeconds)->seconds();

        $company = $this->customer->company_id;

        return $this->withServerVariables($server)->postJson('/api/register', $over + [
            'name' => 'Visitor', 'email' => uniqid('v').'@example.com', 'signup_token' => $challenge['token'],
            'password' => 'password123', 'password_confirmation' => 'password123', 'company_id' => $company,
        ]);
    }

    // ------------------------------------------------------------------ Sybil / fake accounts

    public function test_one_ip_cannot_flood_signups_even_by_rotating_emails(): void
    {
        $this->settings()->set('security.signups_per_ip_per_hour', 3);

        foreach (range(1, 3) as $i) {
            $this->signup(['email' => "bot{$i}@example.com"])->assertCreated();
        }

        $this->signup(['email' => 'bot4@example.com'])->assertStatus(429);
        $this->signup(['email' => 'bot5@example.com'])->assertStatus(429);

        // Another visitor on another address is unaffected.
        $this->signup(['email' => 'human@example.com'], '10.0.0.99')->assertCreated();
        $this->assertSame(4, User::where('name', 'Visitor')->count()); // 3 bots up to the limit + 1 other visitor
    }

    public function test_aliases_of_one_mailbox_count_as_one_account(): void
    {
        $this->signup(['email' => 'Real.Person+shop1@gmail.com'], '10.0.1.1')->assertCreated();

        $this->signup(['email' => 'realperson+shop2@gmail.com'], '10.0.1.2')->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->signup(['email' => 'real.person@googlemail.com'], '10.0.1.3')->assertUnprocessable();
        $this->signup(['email' => 'REALPERSON@gmail.com'], '10.0.1.4')->assertUnprocessable();

        // Admin-created staff follow the same rule.
        $this->assertSame(1, User::where('email_canonical', 'realperson@gmail.com')->count());
        $this->assertSame('realperson@gmail.com', User::where('email', 'real.person+shop1@gmail.com')->value('email_canonical'));
    }

    public function test_disposable_and_blocked_email_domains_are_refused(): void
    {
        $this->signup(['email' => 'x@mailinator.com'], '10.0.2.1')->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->signup(['email' => 'x@inbox.mailinator.com'], '10.0.2.2')->assertUnprocessable(); // subdomains too

        $this->settings()->set('security.blocked_email_domains', "spam.example, evil.test\nbad.example");
        $this->signup(['email' => 'x@evil.test'], '10.0.2.3')->assertUnprocessable();
        $this->signup(['email' => 'x@bad.example'], '10.0.2.4')->assertUnprocessable();

        // The built-in list can be switched off; admin entries stay.
        $this->settings()->set('security.block_disposable_emails', false);
        $this->signup(['email' => 'x@mailinator.com'], '10.0.2.5')->assertCreated();
        $this->signup(['email' => 'y@evil.test'], '10.0.2.6')->assertUnprocessable();

        $this->assertTrue(AuditLog::where('action', 'auth.signup_blocked')->exists());
    }

    public function test_the_signup_kill_switch_stops_new_accounts(): void
    {
        $this->settings()->set('platform.registration_open', false);

        $this->signup([], '10.0.3.1')->assertForbidden();
        $this->assertSame(0, User::where('name', 'Visitor')->count());

        $this->settings()->set('platform.registration_open', true);
        $this->signup([], '10.0.3.2')->assertCreated();
    }

    public function test_weak_passwords_are_rejected(): void
    {
        foreach (['short1', 'onlyletterslong', '12345678901234'] as $i => $weak) {
            $this->signup(['password' => $weak, 'password_confirmation' => $weak], "10.0.4.{$i}")
                ->assertUnprocessable()->assertJsonValidationErrors('password');
        }
        $this->signup(['password' => 'Different1234', 'password_confirmation' => 'Mismatch1234'], '10.0.4.9')->assertUnprocessable();
    }

    // ------------------------------------------------------------------ Bots

    public function test_bots_that_skip_the_human_checks_are_turned_away_without_a_hint(): void
    {
        $message = 'We could not create your account. Please reload the page and try again.';

        // Honeypot filled in.
        $this->signup(['website' => 'http://spam.example'], '10.0.5.1')->assertUnprocessable()->assertJsonPath('message', $message);
        // No token at all, and a forged one.
        $this->postJson('/api/register', ['name' => 'Bot', 'email' => 'bot@example.com', 'password' => 'password123',
            'password_confirmation' => 'password123', 'company_id' => 1])->assertUnprocessable()->assertJsonPath('message', $message);
        $this->signup(['signup_token' => time().'.deadbeef'], '10.0.5.2')->assertUnprocessable()->assertJsonPath('message', $message);
        // Instant submission (a script, not a person).
        $this->signup([], '10.0.5.3', waitSeconds: 0)->assertUnprocessable()->assertJsonPath('message', $message);

        $this->assertSame(0, User::where('name', 'Visitor')->count());
    }

    public function test_a_signup_token_works_once_and_only_from_the_address_that_got_it(): void
    {
        $challenge = $this->withServerVariables(['REMOTE_ADDR' => '10.0.6.1'])->getJson('/api/signup-challenge')->json();
        $this->travel(5)->seconds();
        $payload = fn ($email) => ['name' => 'V', 'email' => $email, 'signup_token' => $challenge['token'], 'password' => 'password123',
            'password_confirmation' => 'password123', 'company_id' => $this->customer->company_id];

        // Stolen/forwarded to another IP.
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.6.2'])->postJson('/api/register', $payload('a@example.com'))->assertUnprocessable();
        // Genuine use...
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.6.1'])->postJson('/api/register', $payload('b@example.com'))->assertCreated();
        // ...and replay of the same token by a script.
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.6.1'])->postJson('/api/register', $payload('c@example.com'))->assertUnprocessable();

        // Expired tokens are refused too.
        $old = $this->withServerVariables(['REMOTE_ADDR' => '10.0.6.1'])->getJson('/api/signup-challenge')->json();
        $this->travel(2)->hours();
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.6.1'])->postJson('/api/register', ['signup_token' => $old['token']] + $payload('d@example.com'))->assertUnprocessable();
    }

    public function test_an_optional_captcha_fails_closed(): void
    {
        $this->settings()->set('security.captcha_provider', 'turnstile');
        $this->settings()->set('security.captcha_site_key', 'site-key');
        $this->settings()->set('security.captcha_secret', 'turnstile-secret');

        $this->getJson('/api/signup-challenge')->assertJsonPath('captcha.provider', 'turnstile')->assertJsonPath('captcha.site_key', 'site-key');
        $this->assertStringNotContainsString('turnstile-secret', $this->getJson('/api/signup-challenge')->getContent());

        // No answer from the visitor.
        $this->signup([], '10.0.7.1')->assertUnprocessable();

        // Provider says no / is down / answers garbage: still refused.
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false], 200)]);
        $this->signup(['captcha_token' => 'x'], '10.0.7.2')->assertUnprocessable();
        Http::fake(['challenges.cloudflare.com/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('down')]);
        $this->signup(['captcha_token' => 'x'], '10.0.7.3')->assertUnprocessable();

        // Provider says yes, and our secret + the visitor's IP are what we sent.
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);
        $this->signup(['captcha_token' => 'good'], '10.0.7.4')->assertCreated();
        Http::assertSent(fn ($r) => $r['secret'] === 'turnstile-secret' && $r['response'] === 'good' && $r['remoteip'] === '10.0.7.4');

        // Turning it off removes the requirement; a missing secret fails closed instead of letting everyone in.
        $this->settings()->forget('security.captcha_secret');
        $this->signup(['captcha_token' => 'good'], '10.0.7.5')->assertUnprocessable();
        $this->settings()->set('security.captcha_provider', 'none');
        $this->signup([], '10.0.7.6')->assertCreated();
    }

    // ------------------------------------------------------------------ Credential attacks

    public function test_password_guessing_is_throttled_per_account_per_ip_and_globally(): void
    {
        $attempt = fn (string $email, string $ip) => $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/login', ['email' => $email, 'password' => 'wrong-password-1']);

        // Hammering one account from one address.
        foreach (range(1, 5) as $_) {
            $attempt($this->customer->email, '10.1.0.1')->assertUnprocessable();
        }
        $attempt($this->customer->email, '10.1.0.1')->assertStatus(429);

        // The real owner, from their own address, is not locked out by the attacker.
        $attempt($this->customer->email, '10.1.0.2')->assertUnprocessable();

        // One machine trying many accounts is stopped by the per-IP ceiling (30/min).
        foreach (range(1, 30) as $i) {
            $attempt("victim{$i}@example.com", '10.1.0.3');
        }
        $attempt('someone-else@example.com', '10.1.0.3')->assertStatus(429);
    }

    public function test_credential_stuffing_across_many_ips_hits_the_per_account_ceiling(): void
    {
        foreach (range(1, 40) as $i) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.2.{$i}.1"])
                ->postJson('/api/login', ['email' => $this->customer->email, 'password' => 'wrong-password-1'])->assertUnprocessable();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.2.200.1'])
            ->postJson('/api/login', ['email' => $this->customer->email, 'password' => 'wrong-password-1'])->assertStatus(429);
    }

    public function test_login_does_the_same_work_whether_or_not_the_email_exists(): void
    {
        $checks = 0;
        Hash::swap(new class($checks) extends \Illuminate\Hashing\HashManager {
            public function __construct(public int &$checks)
            {
                parent::__construct(app());
            }

            public function check($value, $hashedValue, array $options = []): bool
            {
                $this->checks++;

                return parent::check($value, $hashedValue, $options);
            }
        });

        $this->postJson('/api/login', ['email' => 'nobody@example.com', 'password' => 'whatever-123'])->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Invalid credentials.');
        $this->assertSame(1, $checks, 'an unknown email must still cost one password check');

        $this->postJson('/api/login', ['email' => $this->customer->email, 'password' => 'wrong-password-1'])->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Invalid credentials.'); // identical message
        $this->assertSame(2, $checks);
    }

    public function test_sign_ins_and_failures_are_audited_and_sessions_are_capped(): void
    {
        $this->customer->update(['password' => 'password123']);
        $this->settings()->set('security.max_active_tokens', 2);

        $this->postJson('/api/login', ['email' => $this->customer->email, 'password' => 'nope-nope-1'])->assertUnprocessable();
        foreach (range(1, 4) as $_) {
            $this->postJson('/api/login', ['email' => $this->customer->email, 'password' => 'password123'])->assertOk();
        }

        $this->assertSame(2, $this->customer->tokens()->count()); // oldest devices were signed out
        $this->assertSame(1, AuditLog::where('action', 'auth.login_failed')->count());
        $this->assertSame(4, AuditLog::where('action', 'auth.login')->count());
        $this->assertSame($this->customer->email, AuditLog::where('action', 'auth.login_failed')->first()->meta['email']);
    }

    // ------------------------------------------------------------------ Session theft (HttpOnly cookie) & CSRF

    public function test_browsers_get_an_httponly_cookie_and_never_see_the_token(): void
    {
        $this->customer->update(['password' => 'password123']);

        $response = $this->withHeaders(['X-Client' => 'web'])
            ->postJson('/api/login', ['email' => $this->customer->email, 'password' => 'password123'])->assertOk();

        $this->assertArrayNotHasKey('token', $response->json());
        $cookie = collect($response->headers->getCookies())->first(fn ($c) => $c->getName() === 'armorhub_token');
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertSame('/', $cookie->getPath());

        // Scripts and mobile apps keep using Bearer tokens and still get one.
        $this->withoutHeader('X-Client');
        $this->assertNotEmpty($this->postJson('/api/login', ['email' => $this->customer->email, 'password' => 'password123'])->json('token'));
    }

    /** A request exactly as a browser sends it: the session cookie plus whatever headers it adds. */
    private function asBrowser(string $method, string $uri, string $token, array $headers = [], array $json = [])
    {
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $this->call($method, $uri, [], ['armorhub_token' => $token], [], $server, $json ? json_encode($json) : null);
    }

    public function test_the_cookie_authenticates_and_writes_need_a_trusted_origin(): void
    {
        $token = $this->customer->createToken('t')->plainTextToken;
        $where = ['latitude' => 24.7, 'longitude' => 46.7];

        $this->asBrowser('GET', '/api/me', $token)->assertOk()->assertJsonPath('id', $this->customer->id);

        // A page on another site making the victim's browser write (CSRF) is refused...
        $this->asBrowser('PUT', '/api/me/location', $token, ['Origin' => 'https://evil.example'], $where)->assertForbidden();
        $this->asBrowser('PUT', '/api/me/location', $token, [], $where)->assertForbidden(); // no origin at all
        $this->asBrowser('POST', '/api/logout', $token, ['Referer' => 'https://evil.example/page'])->assertForbidden();
        $this->assertSame(1, $this->customer->tokens()->count()); // and nothing happened

        // ...but our own web app (WEB_URL) is fine, and so is a same-origin Referer.
        $this->asBrowser('PUT', '/api/me/location', $token, ['Origin' => 'http://localhost:3000'], $where)->assertOk();
        $this->asBrowser('PUT', '/api/me/location', $token, ['Referer' => 'http://localhost:3000/dashboard'], $where)->assertOk();

        // Bearer-token clients are not browsers and are not subject to the origin check.
        $this->withToken($token)->putJson('/api/me/location', $where)->assertOk();
    }

    public function test_logout_ends_the_session_and_clears_the_cookie(): void
    {
        $token = $this->customer->createToken('t')->plainTextToken;

        $response = $this->asBrowser('POST', '/api/logout', $token, ['Origin' => 'http://localhost:3000'])->assertOk();

        $cookie = collect($response->headers->getCookies())->first(fn ($c) => $c->getName() === 'armorhub_token');
        $this->assertTrue($cookie->isCleared() || $cookie->getExpiresTime() < time());
        $this->assertSame(0, $this->customer->tokens()->count());

        $this->app['auth']->forgetGuards();
        $this->asBrowser('GET', '/api/me', $token)->assertUnauthorized();
    }

    // ------------------------------------------------------------------ Email verification

    public function test_unverified_customers_cannot_order_when_verification_is_required(): void
    {
        $this->settings()->set('security.require_email_verification', 'yes');
        $this->customer->forceFill(['email_verified_at' => null])->save();
        $payload = ['product_id' => $this->product->id, 'target_type' => 'vehicle_part', 'targets' => [$this->hood->id]];

        $this->actingAs($this->customer, 'sanctum')->postJson('/api/orders', $payload)
            ->assertForbidden()->assertJsonPath('code', 'email_unverified');
        $this->getJson('/api/me')->assertJsonPath('email_verification_required', true);

        $this->customer->markEmailAsVerified();
        $this->postJson('/api/orders', $payload)->assertCreated();
        $this->getJson('/api/me')->assertJsonPath('email_verification_required', false);
    }

    public function test_automatic_mode_only_requires_verification_when_mail_is_really_delivered(): void
    {
        $this->customer->forceFill(['email_verified_at' => null])->save();
        $payload = ['product_id' => $this->product->id, 'target_type' => 'vehicle_part', 'targets' => [$this->hood->id]];

        config(['mail.default' => 'log']);
        $this->actingAs($this->customer, 'sanctum')->postJson('/api/orders', $payload)->assertCreated(); // nobody could verify, so don't lock them out

        $this->customer->forceFill(['email_verified_at' => null])->save();
        Order::withoutGlobalScopes()->delete();
        config(['mail.default' => 'smtp']);
        $this->postJson('/api/orders', $payload)->assertForbidden();
    }

    public function test_the_verification_email_and_link(): void
    {
        Notification::fake();
        $this->settings()->set('security.require_email_verification', 'yes');

        $this->signup(['email' => 'fresh@example.com'], '10.3.0.1')->assertCreated();
        $user = User::where('email', 'fresh@example.com')->first();
        $this->assertFalse($user->hasVerifiedEmail());
        Notification::assertSentTo($user, VerifyEmail::class);

        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);

        // A tampered link (e.g. someone else's id) does nothing.
        $other = User::factory()->create(['email_verified_at' => null]);
        $this->get(str_replace("/{$user->id}/", "/{$other->id}/", $url))->assertForbidden();
        $this->assertFalse($other->fresh()->hasVerifiedEmail());

        // A correctly signed link with the wrong hash is refused too.
        $bad = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1('someone@else.com')]);
        $this->get($bad)->assertRedirect('http://localhost:3000/login?verified=0');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());

        $this->get($url)->assertRedirect('http://localhost:3000/login?verified=1');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertTrue(AuditLog::where('action', 'auth.email_verified')->exists());

        // Expired links stop working.
        $expired = URL::temporarySignedRoute('verification.verify', now()->addMinute(), ['id' => $other->id, 'hash' => sha1($other->email)]);
        $this->travel(2)->minutes();
        $this->get($expired)->assertForbidden();
    }

    public function test_resending_the_verification_email_is_throttled(): void
    {
        Notification::fake();
        $this->customer->forceFill(['email_verified_at' => null])->save();

        $this->actingAs($this->customer, 'sanctum');
        foreach (range(1, 3) as $_) {
            $this->postJson('/api/email/resend')->assertOk();
        }
        $this->postJson('/api/email/resend')->assertStatus(429);
        Notification::assertSentToTimes($this->customer, VerifyEmail::class, 3);
    }

    // ------------------------------------------------------------------ Ordering abuse & isolation

    public function test_one_account_cannot_flood_partners_with_requests(): void
    {
        $this->settings()->set('security.max_open_orders', 2);
        $this->actingAs($this->customer, 'sanctum');

        $this->order(false);
        $this->order(false);

        $this->postJson('/api/orders', ['product_id' => $this->product->id, 'target_type' => 'vehicle_part', 'targets' => [$this->hood->id]])
            ->assertUnprocessable()->assertJsonPath('message', 'You have too many unpaid orders. Pay for or cancel one before placing another.');
        $this->assertSame(2, Order::withoutGlobalScopes()->count());
    }

    public function test_ordering_can_be_paused_platform_wide(): void
    {
        $this->settings()->set('platform.orders_enabled', false);

        $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/orders', ['product_id' => $this->product->id, 'target_type' => 'vehicle_part', 'targets' => [$this->hood->id]])
            ->assertStatus(503);
        $this->assertSame(0, Order::withoutGlobalScopes()->count());
    }

    public function test_order_creation_is_rate_limited_per_account(): void
    {
        $this->settings()->set('security.max_open_orders', 100);
        $this->actingAs($this->customer, 'sanctum');
        $payload = ['product_id' => $this->product->id, 'target_type' => 'vehicle_part', 'targets' => [$this->hood->id]];

        foreach (range(1, 10) as $_) {
            $this->postJson('/api/orders', $payload)->assertCreated();
        }
        $this->postJson('/api/orders', $payload)->assertStatus(429);
    }

    public function test_tenants_only_see_and_order_their_own_industrys_products(): void
    {
        $devices = Industry::where('key', 'devices')->first();
        $foreign = Product::create(['industry_id' => $devices->id, 'name' => 'Phone Guard', 'slug' => 'pg', 'base_price' => 1]);
        $shared = Product::create(['industry_id' => null, 'name' => 'Gift Card', 'slug' => 'gc', 'base_price' => 1]);
        $hidden = Product::create(['industry_id' => $this->product->industry_id, 'name' => 'Draft', 'slug' => 'draft', 'base_price' => 1, 'is_active' => false]);

        $names = fn ($user, $q = '') => collect($this->actingAs($user, 'sanctum')->getJson("/api/products{$q}")->assertOk()->json())->pluck('name')->sort()->values()->all();

        $this->assertSame(['Film', 'Gift Card'], $names($this->customer, '?include_inactive=1'));
        $this->assertContains('Phone Guard', $names($this->admin));
        $this->assertContains('Draft', $names($this->admin, '?include_inactive=1'));

        // Direct access by id is hidden too (no probing other tenants' catalogs).
        $this->actingAs($this->customer, 'sanctum');
        $this->getJson("/api/products/{$foreign->id}")->assertNotFound();
        $this->getJson("/api/products/{$hidden->id}")->assertNotFound();
        $this->getJson("/api/products/{$foreign->id}/price-rules")->assertNotFound();
        $this->getJson("/api/products/{$this->product->id}")->assertOk();

        // ...and they cannot be ordered, even by guessing the id.
        foreach ([$foreign, $hidden] as $product) {
            $this->postJson('/api/orders', ['product_id' => $product->id, 'target_type' => 'vehicle_part', 'targets' => [$this->hood->id]])
                ->assertUnprocessable()->assertJsonValidationErrors('product_id');
        }
        $this->assertSame(0, Order::withoutGlobalScopes()->count());
    }

    // ------------------------------------------------------------------ Transport & exposure

    public function test_api_responses_carry_defensive_headers(): void
    {
        $response = $this->actingAs($this->customer, 'sanctum')->getJson('/api/me')->assertOk();

        $response->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString("default-src 'none'", $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertNull($response->headers->get('X-Powered-By'));

        $this->app['auth']->forgetGuards();
        $this->get('/api/me', ['Accept' => 'application/json', 'X-Forwarded-Proto' => 'https'])->assertUnauthorized()
            ->assertHeader('X-Content-Type-Options', 'nosniff'); // errors are covered too
    }

    public function test_cors_only_allows_our_own_web_app_and_never_a_wildcard(): void
    {
        $this->assertNotContains('*', config('cors.allowed_origins'));
        $this->assertTrue(config('cors.supports_credentials'));

        $allowed = $this->withHeaders(['Origin' => 'http://localhost:3000', 'Access-Control-Request-Method' => 'POST'])->options('/api/login');
        $this->assertSame('http://localhost:3000', $allowed->headers->get('Access-Control-Allow-Origin'));

        // With a single allowed origin the header names that origin for every caller; a browser on
        // another site can never match it, so the evil origin is never echoed back.
        $evil = $this->withHeaders(['Origin' => 'https://evil.example', 'Access-Control-Request-Method' => 'POST'])->options('/api/login');
        $this->assertNotSame('https://evil.example', $evil->headers->get('Access-Control-Allow-Origin'));
        $this->assertNotSame('*', $evil->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_every_api_route_requires_authentication_except_the_known_public_ones(): void
    {
        $public = [
            'api/signup-challenge', 'api/register', 'api/login', 'api/forgot-password', 'api/reset-password',
            'api/email/verify/{id}/{hash}', 'api/webhooks/{provider}', 'api/webhooks/stripe', 'up',
            // The public storefront: read-only, throttled and limited to public data (see StorefrontTest).
            'api/public/markets', 'api/partner-applications', 'api/webhooks/shipping/{provider}', 'api/public/showcase', 'api/public/storefronts', 'api/public/storefronts/{slug}', 'api/public/storefronts/{slug}/products',
            'api/public/storefronts/{slug}/products/{id}',
        ];

        $checked = 0;
        foreach (app('router')->getRoutes() as $route) {
            $uri = $route->uri();
            if (! str_starts_with($uri, 'api/') || in_array($uri, $public, true)) {
                continue;
            }

            foreach (array_diff($route->methods(), ['HEAD', 'OPTIONS']) as $method) {
                $path = '/'.preg_replace('/\{[^}]+\}/', '1', $uri);
                $status = $this->json($method, $path)->getStatusCode();
                $this->assertSame(401, $status, "{$method} /{$uri} must require authentication (got {$status})");
                $checked++;
            }
        }

        $this->assertGreaterThan(60, $checked); // the guard really walked the routes
    }

    public function test_the_previously_public_vehicle_lists_now_need_a_login(): void
    {
        $this->getJson('/api/vehicles/makes')->assertUnauthorized();
        $this->getJson('/api/vehicles/makes/1/models')->assertUnauthorized();
        $this->actingAs($this->customer, 'sanctum')->getJson('/api/vehicles/makes')->assertOk();
    }

    // ------------------------------------------------------------------ Reset abuse, incident response, health

    public function test_scripts_cannot_use_password_reset_to_spam_a_victim(): void
    {
        Notification::fake();

        // No human-check token: the same friendly answer, but no email goes out.
        $this->postJson('/api/forgot-password', ['email' => $this->customer->email])->assertOk()
            ->assertJsonPath('message', 'If that email has an account, a reset link has been sent.');
        Notification::assertNothingSent();
        $this->assertTrue(AuditLog::where('action', 'auth.reset_blocked')->exists());

        // A real person still gets theirs, but at most 3 requests an hour per target (the blocked try counts too).
        foreach (range(1, 2) as $_) {
            $token = $this->getJson('/api/signup-challenge')->json('token');
            $this->travel(5)->seconds();
            $this->postJson('/api/forgot-password', ['email' => $this->customer->email, 'signup_token' => $token])->assertOk();
        }
        $token = $this->getJson('/api/signup-challenge')->json('token');
        $this->travel(5)->seconds();
        $this->postJson('/api/forgot-password', ['email' => $this->customer->email, 'signup_token' => $token])->assertStatus(429);
        // ...and Laravel itself only emails one reset per minute per account, so even allowed requests cannot flood the inbox.
        Notification::assertSentToTimes($this->customer, \Illuminate\Auth\Notifications\ResetPassword::class, 1);
    }

    public function test_admins_can_sign_people_out_everywhere(): void
    {
        $this->customer->createToken('a');
        $this->customer->createToken('b');
        $this->actingAs($this->admin, 'sanctum');

        $this->postJson("/api/users/{$this->customer->id}/revoke-sessions")->assertOk();
        $this->assertSame(0, $this->customer->tokens()->count());
        $this->assertTrue(AuditLog::where('action', 'auth.sessions_revoked')->exists());

        // A company owner can do it for their own staff, not for customers, outsiders or admins.
        $owner = User::factory()->create(['company_id' => $this->tech->company_id]);
        $owner->assignRole('company-owner');
        $this->actingAs($owner, 'sanctum')->postJson("/api/users/{$this->tech->id}/revoke-sessions")->assertOk();
        $this->postJson("/api/users/{$this->customer->id}/revoke-sessions")->assertNotFound();
        $this->postJson("/api/users/{$this->admin->id}/revoke-sessions")->assertNotFound();
        $outsider = User::factory()->create();
        $outsider->assignRole('technician');
        $this->postJson("/api/users/{$outsider->id}/revoke-sessions")->assertNotFound();
    }

    public function test_the_emergency_button_revokes_every_session_but_the_admins_own(): void
    {
        $mine = $this->admin->createToken('mine');
        $this->customer->createToken('x');
        $this->manager->createToken('y');

        $this->withToken($mine->plainTextToken)->postJson('/api/admin/security/revoke-all-sessions')->assertOk();

        $this->assertSame(0, $this->customer->tokens()->count() + $this->manager->tokens()->count());
        $this->assertSame(1, $this->admin->tokens()->count());
        $this->assertTrue(AuditLog::where('action', 'security.all_sessions_revoked')->exists());

        foreach ([$this->customer, $this->manager] as $user) {
            $this->actingAs($user, 'sanctum')->postJson('/api/admin/security/revoke-all-sessions')->assertForbidden();
        }
    }

    public function test_the_security_overview_reports_health_and_attack_activity(): void
    {
        foreach (range(1, 3) as $_) {
            $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
                ->postJson('/api/login', ['email' => 'victim@example.com', 'password' => 'wrong-password-1']);
        }

        $report = $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/security')->assertOk()->json();

        $this->assertSame(3, $report['last_24h']['failed_logins']);
        $this->assertSame('198.51.100.7', $report['top_offenders'][0]['ip']);
        $this->assertEquals(3, $report['top_offenders'][0]['attempts']);

        $checks = collect($report['checks'])->keyBy('id');
        $this->assertSame('ok', $checks['cors']['status']);
        $this->assertSame('warn', $checks['captcha']['status']);
        $this->assertSame('warn', $checks['payments']['status']); // simulated in tests
        $this->assertSame('warn', $checks['mail']['status']);

        // Misconfiguration is flagged loudly.
        config(['cors.allowed_origins' => ['*'], 'app.debug' => true]);
        $checks = collect($this->getJson('/api/admin/security')->json('checks'))->keyBy('id');
        $this->assertSame('fail', $checks['cors']['status']);
        $this->assertSame('warn', $checks['debug']['status']);
        $this->artisan('platform:security-check')->assertFailed();
    }

    public function test_two_visitors_behind_one_address_get_different_tokens_in_the_same_second(): void
    {
        $a = $this->getJson('/api/signup-challenge')->json('token');
        $b = $this->getJson('/api/signup-challenge')->json('token');
        $this->assertNotSame($a, $b);

        $this->travel(5)->seconds();
        $company = $this->customer->company_id;
        $register = fn ($token, $email) => $this->postJson('/api/register', ['name' => 'V', 'email' => $email, 'signup_token' => $token,
            'password' => 'password123', 'password_confirmation' => 'password123', 'company_id' => $company]);

        $register($a, 'one@example.com')->assertCreated();
        $register($b, 'two@example.com')->assertCreated(); // not mistaken for a replay
    }
}
