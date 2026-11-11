<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PartnerQuote;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Shipping\ShippingProviders;
use App\Support\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class ShippingTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private array $address = ['name' => 'Layla', 'phone' => '0501234567', 'line1' => '12 King Road', 'city' => 'Riyadh', 'country' => 'sa'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
        $this->product->update(['requires_shipping' => true]);
    }

    /** A paid order for a physical product, with a delivery address. */
    private function parcelOrder(bool $paid = true): int
    {
        $id = $this->actingAs($this->customer, 'sanctum')->postJson('/api/orders', [
            'product_id' => $this->product->id, 'target_type' => 'vehicle_part', 'targets' => [$this->hood->id], 'shipping_address' => $this->address,
        ])->assertCreated()->json('order.id');

        if ($paid) {
            $quote = PartnerQuote::withoutGlobalScopes()->where('order_id', $id)->where('partner_id', $this->shop->id)->first();
            $this->actingAs($this->manager, 'sanctum')->patchJson("/api/partner-quotes/{$quote->id}", ['price' => 200])->assertOk();
            $this->actingAs($this->customer, 'sanctum')->patchJson("/api/partner-quotes/{$quote->id}/accept")->assertOk();
            $this->postJson("/api/orders/{$id}/payments", ['amount' => 1200])->assertCreated();
        }

        return $id;
    }

    private function configureHttp(array $extra = []): void
    {
        $s = app(Settings::class);
        foreach ($extra + [
            'create_url' => 'https://93.184.216.34/v1/shipments', 'auth_value' => 'Bearer carrier-key',
            'response_reference_path' => 'data.id', 'response_tracking_path' => 'data.tracking', 'response_tracking_url_path' => 'data.url', 'response_label_path' => 'data.label',
            'response_cost_path' => 'data.cost', 'carrier_name' => 'Fast Carrier', 'webhook_secret' => 'whsec_ship', 'webhook_status_path' => 'status', 'webhook_tracking_path' => 'tracking',
            'track_url' => 'https://93.184.216.34/v1/track/{tracking}', 'track_events_path' => 'events', 'track_status_path' => 'code',
            'status_map' => 'DLV=delivered, OFD=out_for_delivery',
        ] as $field => $value) {
            $s->set("shipping.http.$field", $value);
        }
    }

    // ------------------------------------------------------------------ ordering

    public function test_physical_products_need_a_valid_delivery_address_and_services_do_not(): void
    {
        $body = ['product_id' => $this->product->id, 'target_type' => 'vehicle_part', 'targets' => [$this->hood->id]];
        $this->actingAs($this->customer, 'sanctum')->postJson('/api/orders', $body)->assertUnprocessable()->assertJsonValidationErrors('shipping_address');
        $this->postJson('/api/orders', $body + ['shipping_address' => $this->address])->assertCreated();

        $bad = $this->address;
        $bad['phone'] = '12345';
        $this->postJson('/api/orders', $body + ['shipping_address' => $bad])->assertUnprocessable()->assertJsonValidationErrors('shipping_address.phone');

        $this->product->update(['requires_shipping' => false]);
        $id = $this->postJson('/api/orders', $body + ['shipping_address' => $this->address])->assertCreated()->json('order.id');
        $this->assertNull(Order::withoutGlobalScopes()->find($id)->shipping_address); // not stored when not needed
    }

    public function test_the_address_is_normalised_and_only_shown_to_those_who_handle_the_parcel(): void
    {
        $id = $this->parcelOrder();
        $stored = Order::withoutGlobalScopes()->find($id)->makeVisible('shipping_address')->shipping_address;
        $this->assertSame('SA', $stored['country']);

        // Lists and the partners' request screens never include it.
        $this->actingAs($this->customer, 'sanctum')->getJson('/api/orders')->assertOk();
        $this->assertStringNotContainsString('King Road', $this->getJson('/api/orders')->getContent());
        $this->assertStringNotContainsString('King Road', $this->actingAs($this->rival, 'sanctum')->getJson('/api/partner/orders')->getContent());
        $this->assertStringNotContainsString('King Road', $this->actingAs($this->manager, 'sanctum')->getJson('/api/partner/jobs')->getContent());

        // The customer and the assigned partner see it through the shipments endpoint; others get nothing.
        $this->actingAs($this->customer, 'sanctum')->getJson("/api/orders/{$id}/shipments")->assertOk()->assertJsonPath('address.line1', '12 King Road')->assertJsonPath('can_manage', false);
        $this->actingAs($this->manager, 'sanctum')->getJson("/api/orders/{$id}/shipments")->assertOk()->assertJsonPath('can_manage', true);
        $this->actingAs($this->rival, 'sanctum')->getJson("/api/orders/{$id}/shipments")->assertNotFound();
    }

    // ------------------------------------------------------------------ manual carrier (any carrier)

    public function test_staff_record_a_manual_shipment_and_the_customer_follows_it(): void
    {
        app(Settings::class)->set('shipping.manual.tracking_url_template', 'https://track.example.com/?n={tracking}');
        $id = $this->parcelOrder();

        $this->actingAs($this->customer, 'sanctum')->postJson("/api/orders/{$id}/shipments", ['carrier' => 'SMSA', 'tracking_number' => 'X1'])->assertNotFound(); // customers cannot book

        $this->actingAs($this->manager, 'sanctum')->postJson("/api/orders/{$id}/shipments", ['provider' => 'manual'])->assertUnprocessable(); // needs carrier + number
        $ship = $this->postJson("/api/orders/{$id}/shipments", ['provider' => 'manual', 'carrier' => 'SMSA', 'tracking_number' => 'AB 12/3'])->assertCreated()
            ->assertJsonPath('status', 'created')->assertJsonPath('carrier', 'SMSA')->assertJsonPath('tracking_url', 'https://track.example.com/?n=AB%2012%2F3')->json();

        $this->postJson("/api/orders/{$id}/shipments", ['provider' => 'manual', 'carrier' => 'SMSA', 'tracking_number' => 'Z'])->assertUnprocessable(); // one live parcel per order

        $this->postJson("/api/shipments/{$ship['id']}/status", ['status' => 'in_transit', 'location' => 'Riyadh hub'])->assertOk()->assertJsonPath('status', 'in_transit');
        $this->postJson("/api/shipments/{$ship['id']}/status", ['status' => 'delivered'])->assertOk()->assertJsonPath('status', 'delivered');
        $this->postJson("/api/shipments/{$ship['id']}/status", ['status' => 'in_transit'])->assertOk()->assertJsonPath('status', 'delivered'); // a finished parcel stays finished
        $this->postJson("/api/shipments/{$ship['id']}/status", ['status' => 'teleported'])->assertUnprocessable();

        $view = $this->actingAs($this->customer, 'sanctum')->getJson("/api/orders/{$id}/shipments")->assertOk();
        $this->assertSame('delivered', $view->json('shipments.0.status'));
        $this->assertGreaterThanOrEqual(3, count($view->json('shipments.0.events')));

        $types = collect($this->getJson("/api/orders/{$id}")->json('events'))->pluck('type');
        $this->assertTrue($types->contains('shipped') && $types->contains('shipment_delivered'));
        $kinds = $this->customer->notifications()->get()->pluck('data.kind');
        $this->assertTrue($kinds->contains('shipped') && $kinds->contains('shipment_delivered'));
    }

    public function test_an_order_must_be_paid_and_physical_before_it_can_ship(): void
    {
        $unpaid = $this->parcelOrder(false);
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/orders/{$unpaid}/shipments", ['provider' => 'manual', 'carrier' => 'X', 'tracking_number' => '1'])
            ->assertUnprocessable()->assertJsonValidationErrors('order');

        $this->product->update(['requires_shipping' => false]);
        $service = $this->order();
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/orders/{$service}/shipments", ['provider' => 'manual', 'carrier' => 'X', 'tracking_number' => '1'])->assertUnprocessable();
    }

    // ------------------------------------------------------------------ custom HTTP carrier

    public function test_the_custom_http_carrier_books_a_parcel_and_keeps_only_https_links(): void
    {
        $this->configureHttp();
        Http::fake(['93.184.216.34/v1/shipments' => Http::response(['data' => ['id' => 'S-9', 'tracking' => 'TRK9', 'url' => 'javascript:alert(1)', 'label' => 'https://93.184.216.34/l.pdf', 'cost' => 31.5]], 201)]);
        $id = $this->parcelOrder();

        $res = $this->actingAs($this->manager, 'sanctum')->postJson("/api/orders/{$id}/shipments", ['provider' => 'http', 'service' => 'express'])->assertCreated()
            ->assertJsonPath('tracking_number', 'TRK9')->assertJsonPath('carrier', 'Fast Carrier')->assertJsonPath('tracking_url', null)->assertJsonPath('label_url', 'https://93.184.216.34/l.pdf');
        $this->assertSame('S-9', $res->json('reference'));

        Http::assertSent(function ($req) {
            $b = $req->data();

            return $req->hasHeader('Authorization', 'Bearer carrier-key') && $b['recipient']['name'] === 'Layla' && $b['recipient']['phone'] === '0501234567'
                && $b['recipient']['country'] === 'SA' && $b['service'] === 'express' && is_numeric($b['declared_value']);
        });
    }

    public function test_carrier_failures_and_unsafe_addresses_are_refused_cleanly(): void
    {
        $id = $this->parcelOrder();
        $this->configureHttp();

        Http::fake(['93.184.216.34/*' => Http::response(['error' => 'bad'], 500)]);
        $this->actingAs($this->manager, 'sanctum')->postJson("/api/orders/{$id}/shipments", ['provider' => 'http'])->assertUnprocessable();
        $this->assertSame(0, Shipment::count());

        Http::fake(['93.184.216.34/*' => Http::response(['data' => ['id' => 'x']], 200)]); // no tracking number
        $this->postJson("/api/orders/{$id}/shipments", ['provider' => 'http'])->assertUnprocessable();

        // An address inside the network is never contacted.
        app(Settings::class)->set('shipping.http.create_url', 'https://169.254.169.254/latest/meta-data');
        $this->postJson("/api/orders/{$id}/shipments", ['provider' => 'http'])->assertUnprocessable();
        $this->assertFalse(app(ShippingProviders::class)->isConfigured('http'));
    }

    public function test_tracking_refresh_reads_the_carriers_events_and_never_goes_backwards(): void
    {
        $this->configureHttp();
        Http::fake(['93.184.216.34/v1/shipments' => Http::response(['data' => ['id' => 'S-1', 'tracking' => 'TRK1']])]);
        $id = $this->parcelOrder();
        $ship = $this->actingAs($this->manager, 'sanctum')->postJson("/api/orders/{$id}/shipments", ['provider' => 'http'])->json();

        Http::fake(['93.184.216.34/v1/track/*' => Http::response(['events' => [
            ['code' => 'in transit', 'time' => '2026-01-02T10:00:00Z'], ['code' => 'OFD', 'time' => '2026-01-03T08:00:00Z'], ['code' => 'weird-unknown'],
        ]])]);
        $this->postJson("/api/shipments/{$ship['id']}/refresh")->assertOk()->assertJsonPath('shipment.status', 'out_for_delivery')->assertJsonPath('updates', 2);
        $this->postJson("/api/shipments/{$ship['id']}/refresh")->assertOk()->assertJsonPath('updates', 0); // nothing new the second time

        // A stale "in transit" arriving late does not undo "out for delivery".
        Http::fake(['93.184.216.34/v1/track/*' => Http::response(['events' => [['code' => 'in transit', 'time' => '2026-01-01T09:00:00Z']]])]);
        $this->postJson("/api/shipments/{$ship['id']}/refresh")->assertOk()->assertJsonPath('shipment.status', 'out_for_delivery');
    }

    public function test_carrier_webhooks_are_signed_deduplicated_and_ignore_strangers(): void
    {
        $this->configureHttp();
        Http::fake(['93.184.216.34/v1/shipments' => Http::response(['data' => ['id' => 'S-2', 'tracking' => 'TRK2']])]);
        $id = $this->parcelOrder();
        $ship = $this->actingAs($this->manager, 'sanctum')->postJson("/api/orders/{$id}/shipments", ['provider' => 'http'])->json();

        $send = function (array $payload, ?string $sig = null) {
            $raw = json_encode($payload);

            return $this->call('POST', '/api/webhooks/shipping/http', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE' => $sig ?? hash_hmac('sha256', $raw, 'whsec_ship')], $raw);
        };

        $send(['status' => 'delivered', 'tracking' => 'TRK2'], 'forged')->assertStatus(400);
        $this->assertSame('created', Shipment::find($ship['id'])->status);

        $send(['status' => 'OFD', 'tracking' => 'TRK2'])->assertOk();
        $send(['status' => 'OFD', 'tracking' => 'TRK2'])->assertOk(); // a retried delivery
        $this->assertSame(1, Shipment::find($ship['id'])->events()->where('status', 'out_for_delivery')->count());

        $send(['status' => 'DLV', 'tracking' => 'TRK2'])->assertOk();
        $this->assertSame('delivered', Shipment::find($ship['id'])->status);
        $this->assertNotNull(Shipment::find($ship['id'])->delivered_at);

        $send(['status' => 'DLV', 'tracking' => 'NOT-OURS'])->assertOk(); // acknowledged, ignored
        $send(['status' => 'who-knows', 'tracking' => 'TRK2'])->assertOk();
        $this->postJson('/api/webhooks/shipping/nope', [])->assertNotFound();
        $this->postJson('/api/webhooks/shipping/manual', [])->assertStatus(400);
    }

    public function test_quotes_come_from_providers_that_support_them(): void
    {
        $id = $this->parcelOrder();
        $this->actingAs($this->manager, 'sanctum')->postJson("/api/orders/{$id}/shipping-rates", ['provider' => 'simulated'])->assertOk()->assertJsonCount(2, 'rates')->assertJsonPath('rates.0.currency', 'USD');
        $this->postJson("/api/orders/{$id}/shipping-rates", ['provider' => 'manual'])->assertOk()->assertJsonCount(0, 'rates');

        $this->configureHttp(['rates_url' => 'https://93.184.216.34/v1/rates', 'rates_list_path' => 'rates', 'rate_service_path' => 'code', 'rate_label_path' => 'name', 'rate_price_path' => 'price', 'rate_eta_path' => 'days']);
        Http::fake(['93.184.216.34/v1/rates' => Http::response(['rates' => [['code' => 'std', 'name' => 'Standard', 'price' => '18.5', 'days' => 3], ['code' => 'bad', 'price' => 'free']]])]);
        $this->postJson("/api/orders/{$id}/shipping-rates", ['provider' => 'http'])->assertOk()->assertJsonCount(1, 'rates')->assertJsonPath('rates.0.price', 18.5)->assertJsonPath('rates.0.eta_days', 3);
    }

    public function test_the_demo_carrier_moves_one_step_per_refresh(): void
    {
        $id = $this->parcelOrder();
        $ship = $this->actingAs($this->admin, 'sanctum')->postJson("/api/orders/{$id}/shipments", ['provider' => 'simulated', 'service' => 'express'])->assertCreated()->assertJsonPath('cost', '45.00')->json();
        foreach (['in_transit', 'out_for_delivery', 'delivered'] as $expected) {
            $this->postJson("/api/shipments/{$ship['id']}/refresh")->assertOk()->assertJsonPath('shipment.status', $expected);
        }
    }

    // ------------------------------------------------------------------ admin configuration

    public function test_only_admins_configure_carriers_and_secrets_stay_secret(): void
    {
        foreach (['customer', 'company-owner', 'partner-manager', 'technician'] as $role) {
            $u = User::factory()->create();
            $u->assignRole($role);
            $this->actingAs($u, 'sanctum')->getJson('/api/admin/shipping/providers')->assertForbidden();
            $this->putJson('/api/admin/shipping/providers/http', ['values' => ['auth_value' => 'x']])->assertForbidden();
        }

        $this->actingAs($this->admin, 'sanctum')->putJson('/api/admin/shipping/providers/http', ['values' => ['auth_value' => 'Bearer top-secret-carrier-key', 'carrier_name' => 'Acme']])->assertOk();
        $list = $this->getJson('/api/admin/shipping/providers')->assertOk();
        $this->assertStringNotContainsString('top-secret-carrier-key', $list->getContent());
        $this->assertStringNotContainsString('top-secret-carrier-key', json_encode(\App\Models\AuditLog::all()->toArray()));
        $this->assertSame(url('/api/webhooks/shipping/http'), collect($list->json('providers'))->firstWhere('key', 'http')['webhook_url']);

        // The tracking link template contains {tracking}; it must still be an https link and nothing else.
        $this->putJson('/api/admin/shipping/providers/manual', ['values' => ['tracking_url_template' => 'https://track.example.com/?n={tracking}']])->assertOk();
        foreach (['javascript:alert(1)', 'http://track.example.com/{tracking}', 'https://x.example.com/<script>', 'not a url'] as $bad) {
            $this->putJson('/api/admin/shipping/providers/manual', ['values' => ['tracking_url_template' => $bad]])->assertUnprocessable();
        }

        $this->postJson('/api/admin/shipping/active', ['provider' => 'http'])->assertUnprocessable(); // not configured yet
        $this->postJson('/api/admin/shipping/active', ['provider' => 'simulated'])->assertOk();
        $this->assertSame('simulated', app(ShippingProviders::class)->activeKey());
        $this->putJson('/api/admin/shipping/providers/http', ['values' => ['nope' => 'x']])->assertUnprocessable();

        // Staff only see providers that are ready to use.
        $keys = collect($this->actingAs($this->manager, 'sanctum')->getJson('/api/shipping-providers')->assertOk()->json('providers'))->pluck('key')->all();
        $this->assertEqualsCanonicalizing(['manual', 'simulated'], $keys);
    }
}
