<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\CommissionDistribution;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\PartnerQuote;
use App\Models\Payment;
use App\Services\Payment\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class StripeTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private const SECRET = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();

        config([
            'platform.payment_gateway' => 'stripe',
            'services.stripe.secret' => 'sk_test_123',
            'services.stripe.webhook_secret' => self::SECRET,
        ]);
    }

    /** An order with an accepted 200 quote: total 1200, ready to pay. */
    private function readyOrder(): int
    {
        $id = $this->order(false);
        $quote = PartnerQuote::withoutGlobalScopes()->where('order_id', $id)->where('partner_id', $this->shop->id)->first();
        $this->actingAs($this->manager, 'sanctum')->patchJson("/api/partner-quotes/{$quote->id}", ['price' => 200])->assertOk();
        $this->actingAs($this->customer, 'sanctum')->patchJson("/api/partner-quotes/{$quote->id}/accept")->assertOk();

        return $id;
    }

    private function checkout(int $id, string $sessionId = 'cs_test_1')
    {
        Http::swap(new \Illuminate\Http\Client\Factory()); // a fresh fake: stubs from earlier calls must not win
        Http::fake(['api.stripe.com/*' => Http::response(['id' => $sessionId, 'url' => "https://checkout.stripe.com/c/pay/{$sessionId}"])]);

        return $this->actingAs($this->customer, 'sanctum')->postJson("/api/orders/{$id}/payments", ['amount' => 1200]);
    }

    private function webhook(array $event, ?string $secret = self::SECRET, ?int $timestamp = null)
    {
        $payload = json_encode($event);
        $t = $timestamp ?? time();
        $signature = hash_hmac('sha256', "{$t}.{$payload}", $secret ?? 'wrong');

        return $this->call('POST', '/api/webhooks/stripe', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => "t={$t},v1={$signature}", 'CONTENT_TYPE' => 'application/json',
        ], $payload);
    }

    private function completedEvent(int $orderId, int $amount = 120000, string $session = 'cs_test_1', string $status = 'paid'): array
    {
        return ['type' => 'checkout.session.completed', 'data' => ['object' => [
            'id' => $session, 'payment_status' => $status, 'amount_total' => $amount, 'currency' => 'usd',
            'metadata' => ['order_id' => (string) $orderId],
        ]]];
    }

    public function test_checkout_session_is_created_and_the_order_stays_unpaid(): void
    {
        $id = $this->readyOrder();

        $this->checkout($id)->assertStatus(202)
            ->assertJsonPath('checkout_url', 'https://checkout.stripe.com/c/pay/cs_test_1');

        Http::assertSent(function (HttpRequest $request) use ($id) {
            $body = $request->data();
            $this->assertSame('https://api.stripe.com/v1/checkout/sessions', $request->url());
            $this->assertSame('Basic '.base64_encode('sk_test_123:'), $request->header('Authorization')[0]);
            $this->assertSame('payment', $body['mode']);
            $this->assertSame((string) $id, $body['metadata']['order_id']);
            $this->assertSame('usd', $body['line_items'][0]['price_data']['currency']);
            $this->assertSame(120000, $body['line_items'][0]['price_data']['unit_amount']);
            $this->assertSame("http://localhost:3000/dashboard/orders/{$id}?checkout=success", $body['success_url']);

            return true;
        });

        $this->assertSame(OrderStatus::PartnerSelected, Order::withoutGlobalScopes()->find($id)->status);
        $payment = Payment::withoutGlobalScopes()->where('order_id', $id)->first();
        $this->assertSame('pending', $payment->status);
        $this->assertSame('cs_test_1', $payment->transaction_id);
        $this->assertSame(0, CommissionDistribution::count());
    }

    public function test_the_signed_webhook_pays_the_order_exactly_once(): void
    {
        $id = $this->readyOrder();
        $this->checkout($id)->assertStatus(202);

        $this->webhook($this->completedEvent($id))->assertOk();

        $order = Order::withoutGlobalScopes()->find($id);
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame(1, Payment::withoutGlobalScopes()->where('order_id', $id)->count()); // the pending row was completed, not duplicated
        $this->assertSame('completed', Payment::withoutGlobalScopes()->where('order_id', $id)->value('status'));
        $this->assertEquals([42, 30, 48], CommissionDistribution::orderBy('id')->pluck('amount')->map(fn ($a) => (float) $a)->all());
        $this->assertSame(1, OrderEvent::where('type', 'paid')->count());

        // Stripe retries and double deliveries change nothing.
        $this->webhook($this->completedEvent($id))->assertOk();
        $this->assertSame(1, Payment::withoutGlobalScopes()->where('order_id', $id)->count());
        $this->assertSame(3, CommissionDistribution::count());
        $this->assertSame(1, OrderEvent::where('type', 'paid')->count());

        // The partner is told, as in the simulated flow.
        $this->assertContains('New paid job', $this->manager->notifications()->get()->pluck('data.title')->all());
    }

    public function test_forged_stale_and_tampered_webhooks_are_rejected(): void
    {
        $id = $this->readyOrder();
        $this->checkout($id)->assertStatus(202);
        $unpaid = fn () => $this->assertSame(OrderStatus::PartnerSelected, Order::withoutGlobalScopes()->find($id)->status);

        // Wrong secret, missing signature, replay of an old (valid) signature.
        $this->webhook($this->completedEvent($id), 'someone-elses-secret')->assertStatus(400);
        $this->postJson('/api/webhooks/stripe', $this->completedEvent($id))->assertStatus(400);
        $this->webhook($this->completedEvent($id), self::SECRET, time() - 3600)->assertStatus(400);
        $unpaid();

        // A real signature but the wrong amount (someone paid 1 for a 1200 order).
        $this->webhook($this->completedEvent($id, 100))->assertOk();
        $unpaid();

        // Checkout finished but not actually paid (e.g. delayed payment method).
        $this->webhook($this->completedEvent($id, 120000, 'cs_test_1', 'unpaid'))->assertOk();
        $unpaid();

        // Unknown order / unrelated event types are acknowledged and ignored.
        $this->webhook($this->completedEvent(999999))->assertOk();
        $this->webhook(['type' => 'customer.created', 'data' => ['object' => []]])->assertOk();
        $unpaid();
        $this->assertSame(0, CommissionDistribution::count());
    }

    public function test_an_unconfigured_webhook_secret_accepts_nothing(): void
    {
        config(['services.stripe.webhook_secret' => null]);

        $this->webhook($this->completedEvent(1), '')->assertStatus(400);
    }

    public function test_an_expired_checkout_is_recorded_and_the_customer_can_try_again(): void
    {
        $id = $this->readyOrder();
        $this->checkout($id, 'cs_old')->assertStatus(202);

        $this->webhook(['type' => 'checkout.session.expired', 'data' => ['object' => ['id' => 'cs_old']]])->assertOk();
        $this->assertSame('expired', Payment::withoutGlobalScopes()->where('transaction_id', 'cs_old')->value('status'));

        $this->checkout($id, 'cs_new')->assertStatus(202);
        $this->webhook($this->completedEvent($id, 120000, 'cs_new'))->assertOk();

        $this->assertSame(OrderStatus::Paid, Order::withoutGlobalScopes()->find($id)->status);
        $this->assertSame(['completed', 'expired'], Payment::withoutGlobalScopes()->where('order_id', $id)->pluck('status')->sort()->values()->all());
    }

    public function test_failures_are_reported_cleanly(): void
    {
        $id = $this->readyOrder();

        // Stripe rejects the request.
        Http::fake(['api.stripe.com/*' => Http::response(['error' => ['message' => 'bad']], 400)]);
        $this->actingAs($this->customer, 'sanctum')->postJson("/api/orders/{$id}/payments", ['amount' => 1200])
            ->assertStatus(402)->assertJsonPath('message', 'The payment could not be started.');

        // Stripe is unreachable.
        Http::fake(['api.stripe.com/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout')]);
        $this->postJson("/api/orders/{$id}/payments", ['amount' => 1200])->assertStatus(503);

        // No secret configured.
        config(['services.stripe.secret' => null]);
        $this->postJson("/api/orders/{$id}/payments", ['amount' => 1200])->assertStatus(503);

        $this->assertSame(0, Payment::withoutGlobalScopes()->count());
        $this->assertSame(OrderStatus::PartnerSelected, Order::withoutGlobalScopes()->find($id)->status);
    }

    public function test_minor_units_follow_the_currency(): void
    {
        $this->assertSame(120050, Money::minorUnits('1200.50', 'USD'));
        $this->assertSame(1200, Money::minorUnits(1200, 'jpy'));
        $this->assertSame(1200500, Money::minorUnits(1200.5, 'KWD'));
        $this->assertSame(1999, Money::minorUnits(19.99, 'SAR')); // float rounding
    }
}
