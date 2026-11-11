<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\PartnerQuote;
use App\Models\Payment;
use App\Services\Payment\PaymentProviders;
use App\Support\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** Payment providers are drivers chosen and configured by admins at runtime. */
class PaymentProvidersTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private const GATEWAY = 'https://93.184.216.34/v1/payments'; // a public address: no DNS needed

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
        config(['platform.payment_gateway' => 'simulated']);
    }

    private function admin()
    {
        return $this->actingAs($this->admin, 'sanctum');
    }

    private function readyOrder(): int
    {
        $id = $this->order(false);
        $quote = PartnerQuote::withoutGlobalScopes()->where('order_id', $id)->where('partner_id', $this->shop->id)->first();
        $this->actingAs($this->manager, 'sanctum')->patchJson("/api/partner-quotes/{$quote->id}", ['price' => 200])->assertOk();
        $this->actingAs($this->customer, 'sanctum')->patchJson("/api/partner-quotes/{$quote->id}/accept")->assertOk();

        return $id;
    }

    private function configureStripe(string $key = 'sk_admin_set', string $hook = 'whsec_admin_set'): void
    {
        $this->admin()->putJson('/api/admin/payments/providers/stripe', ['values' => ['secret_key' => $key, 'webhook_secret' => $hook]])->assertOk();
    }

    /** Fully configure the custom HTTP gateway (every required field). */
    private function configureHttp(array $over = []): void
    {
        $values = $over + [
            'create_url' => self::GATEWAY, 'auth_header' => 'X-Api-Key', 'auth_value' => 'gw_secret_key', 'content_type' => 'json',
            'request_body' => '{"amount": "{amount_minor}", "currency": "{currency}", "reference": "{order_id}", "note": "Pay for {description}", "return": {"ok": "{success_url}", "back": "{cancel_url}"}}',
            'response_url_path' => 'data.pay_url', 'response_reference_path' => 'data.id',
            'webhook_secret' => 'hook_secret', 'webhook_signature_header' => 'X-Signature', 'webhook_signature_encoding' => 'hex',
            'webhook_status_path' => 'data.status', 'webhook_paid_values' => 'paid, succeeded',
            'webhook_order_path' => 'data.reference', 'webhook_reference_path' => 'data.id', 'webhook_amount_path' => 'data.amount',
            'webhook_amount_unit' => 'minor', 'webhook_currency_path' => 'data.currency',
        ];
        $this->admin()->putJson('/api/admin/payments/providers/http', ['values' => $values])->assertOk();
    }

    private function activate(string $key)
    {
        return $this->admin()->postJson('/api/admin/payments/active', ['provider' => $key]);
    }

    private function signedWebhook(string $provider, array $body, string $secret = 'hook_secret', string $encoding = 'hex', string $prefix = '', string $header = 'X-Signature')
    {
        $raw = json_encode($body);
        $mac = hash_hmac('sha256', $raw, $secret, true);

        return $this->call('POST', "/api/webhooks/{$provider}", [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_'.strtoupper(str_replace('-', '_', $header)) => $prefix.($encoding === 'base64' ? base64_encode($mac) : bin2hex($mac)),
        ], $raw);
    }

    // ------------------------------------------------------------------ Switching providers from the admin panel

    public function test_the_admin_sees_every_provider_and_secrets_stay_hidden(): void
    {
        $this->configureStripe('sk_super_secret_value', 'whsec_super_secret_value');

        $response = $this->admin()->getJson('/api/admin/payments/providers')->assertOk();
        $body = $response->getContent();

        $this->assertStringNotContainsString('sk_super_secret_value', $body);
        $this->assertStringNotContainsString('whsec_super_secret_value', $body);
        $this->assertStringNotContainsString('gw_secret_key', $body);

        $providers = collect($response->json('providers'))->keyBy('key');
        $this->assertSame(['simulated', 'stripe', 'http'], $providers->keys()->all());
        $this->assertSame('simulated', $response->json('active'));
        $this->assertTrue($providers['stripe']['configured']);
        $this->assertFalse($providers['http']['configured']);
        $this->assertSame('http://localhost/api/webhooks/stripe', $providers['stripe']['webhook_url']);
        $secret = collect($providers['stripe']['fields'])->firstWhere('key', 'secret_key');
        $this->assertNull($secret['value']);
        $this->assertTrue($secret['has_value']);

        // Stored encrypted, never in the audit trail.
        $this->assertStringNotContainsString('sk_super_secret_value', DB::table('settings')->where('key', 'payments.stripe.secret_key')->value('value'));
        $this->assertStringNotContainsString('sk_super_secret_value', json_encode(AuditLog::all()->toArray()));
    }

    public function test_only_platform_admins_manage_payment_providers(): void
    {
        foreach ([$this->customer, $this->manager, $this->tech] as $user) {
            $this->actingAs($user, 'sanctum');
            $this->getJson('/api/admin/payments/providers')->assertForbidden();
            $this->putJson('/api/admin/payments/providers/stripe', ['values' => ['secret_key' => 'x']])->assertForbidden();
            $this->postJson('/api/admin/payments/active', ['provider' => 'stripe'])->assertForbidden();
            $this->getJson('/api/admin/payments')->assertForbidden();
        }
    }

    public function test_a_provider_must_be_configured_before_it_can_be_activated(): void
    {
        $this->activate('stripe')->assertUnprocessable()->assertJsonValidationErrors('provider');
        $this->activate('http')->assertUnprocessable();
        $this->activate('nope')->assertNotFound();
        $this->assertSame('simulated', app(PaymentProviders::class)->activeKey());

        $this->configureStripe();
        $this->activate('stripe')->assertOk()->assertJsonPath('active', 'stripe');
        $this->assertTrue(AuditLog::where('action', 'payments.provider_activated')->exists());
    }

    public function test_credentials_entered_in_the_admin_panel_are_what_checkout_uses(): void
    {
        $this->configureStripe('sk_from_the_panel', 'whsec_from_the_panel');
        $this->activate('stripe')->assertOk();
        $id = $this->readyOrder();

        Http::fake(['api.stripe.com/*' => Http::response(['id' => 'cs_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_1'])]);
        $this->actingAs($this->customer, 'sanctum')->postJson("/api/orders/{$id}/payments", ['amount' => 1200])
            ->assertStatus(202)->assertJsonPath('checkout_url', 'https://checkout.stripe.com/c/pay/cs_1');

        Http::assertSent(fn (HttpRequest $r) => $r->header('Authorization')[0] === 'Basic '.base64_encode('sk_from_the_panel:'));
        $this->assertSame('stripe', Payment::withoutGlobalScopes()->where('order_id', $id)->value('provider'));
    }

    public function test_switching_provider_takes_effect_immediately_and_old_checkouts_still_complete(): void
    {
        $this->configureStripe('sk_a', 'whsec_a');
        $this->activate('stripe');
        $id = $this->readyOrder();
        Http::fake(['api.stripe.com/*' => Http::response(['id' => 'cs_old', 'url' => 'https://checkout.stripe.com/c/pay/cs_old'])]);
        $this->actingAs($this->customer, 'sanctum')->postJson("/api/orders/{$id}/payments", ['amount' => 1200])->assertStatus(202);

        // The admin changes provider while that customer is still on Stripe's page.
        $this->activate('simulated')->assertOk();
        $other = $this->order(false);
        $this->assertSame('simulated', app(PaymentProviders::class)->activeKey());

        // Stripe's confirmation for the earlier checkout is still honoured.
        $payload = json_encode(['type' => 'checkout.session.completed', 'data' => ['object' => [
            'id' => 'cs_old', 'payment_status' => 'paid', 'amount_total' => 120000, 'currency' => 'usd', 'metadata' => ['order_id' => (string) $id]]]]);
        $t = now()->timestamp;
        $this->call('POST', '/api/webhooks/stripe', [], [], [], ['CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => "t={$t},v1=".hash_hmac('sha256', "{$t}.{$payload}", 'whsec_a')], $payload)->assertOk();

        $this->assertSame(OrderStatus::Paid, Order::withoutGlobalScopes()->find($id)->status);
        $this->assertSame('stripe', Payment::withoutGlobalScopes()->where('order_id', $id)->value('provider'));
        $this->assertNotNull($other);
    }

    public function test_the_stripe_connection_test_reports_good_and_bad_keys(): void
    {
        $this->admin()->postJson('/api/admin/payments/providers/stripe/test')->assertOk()->assertJsonPath('ok', false);

        $this->configureStripe();
        Http::fake(['api.stripe.com/v1/balance' => Http::response(['available' => []])]);
        $this->postJson('/api/admin/payments/providers/stripe/test')->assertJsonPath('ok', true);

        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(['api.stripe.com/v1/balance' => Http::response(['error' => ['message' => 'Invalid API Key']], 401)]);
        $this->postJson('/api/admin/payments/providers/stripe/test')->assertJsonPath('ok', false)->assertJsonPath('message', 'Stripe rejected the key: Invalid API Key');
        $this->postJson('/api/admin/payments/providers/missing/test')->assertNotFound();
    }

    public function test_webhooks_for_unknown_or_unconfigured_providers_are_rejected_and_logged(): void
    {
        $this->postJson('/api/webhooks/nonexistent', [])->assertNotFound();

        // Configured nowhere: nothing can be verified, so nothing is accepted.
        config(['services.stripe.secret' => null, 'services.stripe.webhook_secret' => null]);
        $this->postJson('/api/webhooks/stripe', ['type' => 'checkout.session.completed'])->assertStatus(400);
        $this->postJson('/api/webhooks/simulated', [])->assertStatus(400);

        $rejected = AuditLog::where('action', 'payments.webhook_rejected')->get();
        $this->assertCount(2, $rejected);
        $this->assertSame('stripe', $rejected->first()->meta['provider']);
    }

    // ------------------------------------------------------------------ The configurable HTTP provider

    public function test_a_custom_gateway_can_be_connected_entirely_from_settings(): void
    {
        $this->configureHttp();
        $this->activate('http')->assertOk()->assertJsonPath('active', 'http');
        $id = $this->readyOrder();

        Http::fake([self::GATEWAY => Http::response(['data' => ['id' => 'pay_77', 'pay_url' => 'https://pay.gateway.example/p/pay_77']])]);
        $this->actingAs($this->customer, 'sanctum')->postJson("/api/orders/{$id}/payments", ['amount' => 1200])
            ->assertStatus(202)->assertJsonPath('checkout_url', 'https://pay.gateway.example/p/pay_77');

        Http::assertSent(function (HttpRequest $r) use ($id) {
            $body = $r->data();
            $this->assertSame(self::GATEWAY, $r->url());
            $this->assertSame('gw_secret_key', $r->header('X-Api-Key')[0]);
            $this->assertSame(120000, $body['amount']);                 // a lone placeholder keeps its number type
            $this->assertSame('USD', $body['currency']);
            $this->assertSame((string) $id, $body['reference']);
            $this->assertSame("Pay for Order #{$id}", $body['note']);   // placeholders inside text are interpolated
            $this->assertSame("http://localhost:3000/dashboard/orders/{$id}?checkout=success", $body['return']['ok']);

            return true;
        });

        $payment = Payment::withoutGlobalScopes()->where('order_id', $id)->first();
        $this->assertSame(['pending', 'http', 'pay_77'], [$payment->status, $payment->provider, $payment->transaction_id]);

        // The gateway's signed confirmation pays the order exactly once.
        $hook = ['data' => ['id' => 'pay_77', 'status' => 'PAID', 'reference' => (string) $id, 'amount' => 120000, 'currency' => 'usd']];
        $this->signedWebhook('http', $hook)->assertOk();
        $this->signedWebhook('http', $hook)->assertOk();

        $this->assertSame(OrderStatus::Paid, Order::withoutGlobalScopes()->find($id)->status);
        $this->assertSame(1, Payment::withoutGlobalScopes()->where('order_id', $id)->count());
        $this->assertSame('completed', Payment::withoutGlobalScopes()->where('order_id', $id)->value('status'));
        $this->assertSame(3, \App\Models\CommissionDistribution::count());
    }

    public function test_custom_gateway_webhooks_must_be_signed_and_honest(): void
    {
        $this->configureHttp();
        $this->activate('http');
        $id = $this->readyOrder();
        Http::fake([self::GATEWAY => Http::response(['data' => ['id' => 'pay_1', 'pay_url' => 'https://pay.gateway.example/p/1']])]);
        $this->actingAs($this->customer, 'sanctum')->postJson("/api/orders/{$id}/payments", ['amount' => 1200])->assertStatus(202);
        $good = ['data' => ['id' => 'pay_1', 'status' => 'paid', 'reference' => (string) $id, 'amount' => 120000, 'currency' => 'USD']];
        $unpaid = fn () => $this->assertSame(OrderStatus::PartnerSelected, Order::withoutGlobalScopes()->find($id)->status);

        // Wrong secret, unsigned, tampered body.
        $this->signedWebhook('http', $good, 'attacker-guess')->assertStatus(400);
        $this->postJson('/api/webhooks/http', $good)->assertStatus(400);
        $raw = json_encode($good);
        $sig = bin2hex(hash_hmac('sha256', $raw, 'hook_secret', true));
        $this->call('POST', '/api/webhooks/http', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE' => $sig], str_replace('120000', '1', $raw))->assertStatus(400);
        $unpaid();

        // Signed, but underpaid or wrong currency: acknowledged, ignored, and logged.
        $this->signedWebhook('http', ['data' => ['amount' => 100] + $good['data']])->assertOk();
        $this->signedWebhook('http', ['data' => ['currency' => 'EUR'] + $good['data']])->assertOk();
        $unpaid();
        $this->assertSame(2, AuditLog::where('action', 'payments.webhook_mismatch')->count());

        // Signed but not a paid status, or missing fields.
        $this->signedWebhook('http', ['data' => ['status' => 'pending'] + $good['data']])->assertOk();
        $this->signedWebhook('http', ['data' => ['status' => 'paid', 'id' => 'x']])->assertStatus(400);
        $unpaid();

        // Variations: base64 + "sha256=" prefix + a custom header name + a whole-unit amount.
        $this->configureHttp(['webhook_signature_encoding' => 'base64', 'webhook_signature_header' => 'X-Hub-Signature', 'webhook_amount_unit' => 'major']);
        $this->signedWebhook('http', ['data' => ['amount' => 1200.00] + $good['data']], 'hook_secret', 'base64', 'sha256=', 'X-Hub-Signature')->assertOk();
        $this->assertSame(OrderStatus::Paid, Order::withoutGlobalScopes()->find($id)->status);
    }

    public function test_the_custom_gateway_handles_form_posts_and_bad_replies(): void
    {
        $this->configureHttp(['content_type' => 'form']);
        $this->activate('http');
        $id = $this->readyOrder();

        Http::fake([self::GATEWAY => Http::response(['data' => ['id' => 'p', 'pay_url' => 'https://pay.gateway.example/p']])]);
        $this->actingAs($this->customer, 'sanctum')->postJson("/api/orders/{$id}/payments", ['amount' => 1200])->assertStatus(202);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->header('Content-Type')[0], 'x-www-form-urlencoded') && $r['return[ok]'] !== null && (string) $r['reference'] === (string) $id);

        foreach ([
            Http::response(['data' => ['id' => 'p', 'pay_url' => 'javascript:alert(1)']]),   // never send customers to a non-https link
            Http::response(['data' => ['id' => 'p', 'pay_url' => 'http://insecure.example']]),
            Http::response(['data' => ['pay_url' => 'https://ok.example']]),                   // no payment id
            Http::response(['error' => 'nope'], 500),
        ] as $reply) {
            Http::swap(new \Illuminate\Http\Client\Factory());
            Http::fake([self::GATEWAY => $reply]);
            $this->postJson("/api/orders/{$id}/payments", ['amount' => 1200])->assertStatus(402)->assertJsonPath('message', 'The payment could not be started.');
        }

        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake([self::GATEWAY => fn () => throw new \Illuminate\Http\Client\ConnectionException('down')]);
        $this->postJson("/api/orders/{$id}/payments", ['amount' => 1200])->assertStatus(503);
    }

    public function test_the_gateway_url_cannot_point_at_internal_networks(): void
    {
        foreach ([
            'http://93.184.216.34/pay',                  // not https
            'https://127.0.0.1/pay', 'https://localhost/pay', 'https://10.0.0.5/pay', 'https://192.168.1.20/pay',
            'https://169.254.169.254/latest/meta-data',  // cloud metadata
            'https://[::1]/pay', 'https://user:pass@93.184.216.34/pay', 'not a url',
        ] as $url) {
            $this->configureHttp(['create_url' => $url]);
            $this->assertFalse(app(PaymentProviders::class)->isConfigured('http'), "{$url} must not be accepted");
            $this->activate('http')->assertUnprocessable();
        }

        $this->configureHttp(['create_url' => self::GATEWAY]);
        $this->assertTrue(app(PaymentProviders::class)->isConfigured('http'));
    }

    public function test_an_incomplete_or_invalid_gateway_configuration_is_not_usable(): void
    {
        $this->configureHttp(['request_body' => 'this is not json']);
        $this->assertFalse(app(PaymentProviders::class)->isConfigured('http'));

        // Clearing a required field (here the amount path) makes the gateway unusable: underpayments could not be detected.
        $this->configureHttp(['request_body' => '{"a": 1}']);
        $this->assertTrue(app(PaymentProviders::class)->isConfigured('http'));
        $this->admin()->putJson('/api/admin/payments/providers/http', ['values' => ['webhook_amount_path' => '']])->assertOk();
        $this->assertFalse(app(PaymentProviders::class)->isConfigured('http'));
        $this->activate('http')->assertUnprocessable();

        // A blank secret, by contrast, keeps the saved one.
        $this->configureHttp();
        $this->admin()->putJson('/api/admin/payments/providers/http', ['values' => ['auth_value' => '']])->assertOk();
        $this->assertTrue(app(PaymentProviders::class)->isConfigured('http'));

        $this->admin()->putJson('/api/admin/payments/providers/http', ['values' => ['content_type' => 'xml']])->assertUnprocessable();
        $this->admin()->putJson('/api/admin/payments/providers/http', ['values' => ['bogus' => 'x']])->assertUnprocessable();
        $this->admin()->putJson('/api/admin/payments/providers/nope', ['values' => ['a' => 'b']])->assertNotFound();
    }

    public function test_the_payments_monitor_lists_payments_with_their_provider(): void
    {
        $id = $this->readyOrder();
        $this->actingAs($this->customer, 'sanctum')->postJson("/api/orders/{$id}/payments", ['amount' => 1200])->assertCreated();

        $rows = $this->admin()->getJson('/api/admin/payments')->assertOk()->json('data');
        $this->assertSame(['completed', 'simulated', $id], [$rows[0]['status'], $rows[0]['provider'], $rows[0]['order_id']]);
        $this->getJson('/api/admin/payments?status=pending')->assertJsonCount(0, 'data');
    }

    public function test_the_admin_overview_summarises_the_platform_and_flags_what_needs_attention(): void
    {
        $this->readyOrder();
        $this->actingAs($this->customer, 'sanctum')->postJson('/api/orders/1/payments', ['amount' => 1200])->assertCreated();
        // A forged payment webhook and a few failed sign-ins show up as things to look at.
        $this->postJson('/api/webhooks/stripe', ['x' => 1])->assertStatus(400);
        $this->postJson('/api/login', ['email' => 'nobody@example.com', 'password' => 'wrong-password-1']);

        $overview = $this->admin()->getJson('/api/admin/overview')->assertOk()->json();
        $numbers = collect($overview['numbers'])->pluck('value', 'key');

        $this->assertEquals(1200, $numbers['revenue']);
        $this->assertEquals(120, $numbers['commission']);
        $this->assertEquals(1, $numbers['orders']);
        $this->assertSame('Simulated (demo, no money moves)', $overview['payments']['provider']);
        $this->assertSame(1, $overview['orders_by_status']['paid']);

        $attention = collect($overview['attention'])->keyBy('key');
        $this->assertSame(1, $attention['payment_issues']['count']);
        $this->assertSame(1, $attention['failed_logins']['count']);
        $this->assertGreaterThanOrEqual(1, $attention['security_warnings']['count']);
        $this->assertSame(3, $attention['unpaid_payouts']['count']);
        $this->assertNotEmpty($overview['recent_activity']);

        foreach ([$this->customer, $this->manager] as $user) {
            $this->actingAs($user, 'sanctum')->getJson('/api/admin/overview')->assertForbidden();
        }
    }

    public function test_connections_are_pinned_to_the_validated_address(): void
    {
        // An IP literal needs no pinning; a name that cannot be resolved yields no option rather than a bad one.
        $this->assertSame([], \App\Support\Security\SafeUrl::curlOptions('https://93.184.216.34/pay'));
        $this->assertSame([], \App\Support\Security\SafeUrl::curlOptions('https://does-not-resolve.invalid/pay'));
        $this->assertSame([], \App\Support\Security\SafeUrl::curlOptions('nonsense'));

        // When the name resolves (here: localhost, in a sandbox where it always does) the option pins host:port:ip.
        $options = \App\Support\Security\SafeUrl::curlOptions('https://localhost:8443/pay');
        $this->assertStringStartsWith('localhost:8443:127.', $options[CURLOPT_RESOLVE][0] ?? '');
    }
}
