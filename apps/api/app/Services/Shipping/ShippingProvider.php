<?php

namespace App\Services\Shipping;

use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Http\Request;

/**
 * A shipping/carrier driver. Like payment providers, which ones are usable and their credentials are
 * settings an admin edits at runtime (`shipping.<key>.<field>`). Adding a carrier means adding one class
 * like this and registering it, or connecting it from the admin panel with the custom HTTP driver.
 */
interface ShippingProvider
{
    public function key(): string;

    public function label(): string;

    public function help(): string;

    /** Same field spec as payment providers. @return array<string, array<string, mixed>> */
    public function fields(): array;

    /** @param  array<string, mixed>  $config */
    public function isConfigured(array $config): bool;

    /**
     * Price options for sending this order to an address; empty when the provider cannot quote.
     *
     * @param  array<string, mixed>  $address
     * @param  array<string, mixed>  $config
     * @return list<array{service: string, label: string, price: float, currency: string, eta_days: ?int}>
     */
    public function quote(Order $order, array $address, array $config): array;

    /**
     * Book the shipment with the carrier.
     *
     * @param  array<string, mixed>  $address
     * @param  array<string, mixed>  $input   what staff supplied: service, and for manual shipments carrier + tracking_number
     * @param  array<string, mixed>  $config
     * @return array{reference: ?string, tracking_number: ?string, tracking_url: ?string, label_url: ?string, cost: ?float, carrier: ?string}
     *
     * @throws ShippingUnavailable
     */
    public function create(Order $order, array $address, array $input, array $config): array;

    /**
     * Ask the carrier where the parcel is. Empty when the provider has no tracking API.
     *
     * @param  array<string, mixed>  $config
     * @return list<TrackingEvent>
     *
     * @throws ShippingUnavailable
     */
    public function track(Shipment $shipment, array $config): array;

    /**
     * Verify and read a carrier webhook; null for events we ignore.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws \App\Services\Payment\InvalidWebhook
     */
    public function parseWebhook(Request $request, array $config): ?TrackingEvent;

    /** @param  array<string, mixed>  $config  @return array{ok: bool, message: string} */
    public function test(array $config): array;
}
