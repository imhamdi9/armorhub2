<?php

namespace App\Services\Shipping\Providers;

use App\Models\Order;
use App\Models\Shipment;
use App\Services\Shipping\ShippingProvider;
use App\Services\Shipping\TrackingEvent;
use Illuminate\Http\Request;

/** A pretend carrier for demos and tests: books instantly and moves one step forward each time tracking is refreshed. */
class SimulatedProvider implements ShippingProvider
{
    private const NEXT = [
        Shipment::CREATED => Shipment::IN_TRANSIT,
        Shipment::IN_TRANSIT => Shipment::OUT_FOR_DELIVERY,
        Shipment::OUT_FOR_DELIVERY => Shipment::DELIVERED,
        Shipment::FAILED => Shipment::IN_TRANSIT,
    ];

    public function key(): string
    {
        return 'simulated';
    }

    public function label(): string
    {
        return 'Demo carrier (no real shipments)';
    }

    public function help(): string
    {
        return 'For trying the flow: it creates fake tracking numbers and advances the status each time tracking is refreshed. Do not use for real orders.';
    }

    public function fields(): array
    {
        return [];
    }

    public function isConfigured(array $config): bool
    {
        return true;
    }

    public function quote(Order $order, array $address, array $config): array
    {
        return [
            ['service' => 'standard', 'label' => 'Standard (2-4 days)', 'price' => 25.0, 'currency' => $order->currency, 'eta_days' => 3],
            ['service' => 'express', 'label' => 'Express (next day)', 'price' => 45.0, 'currency' => $order->currency, 'eta_days' => 1],
        ];
    }

    public function create(Order $order, array $address, array $input, array $config): array
    {
        $tracking = 'DEMO'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT).strtoupper(bin2hex(random_bytes(2)));

        return ['reference' => 'sim-'.$order->id, 'tracking_number' => $tracking, 'tracking_url' => null, 'label_url' => null,
            'cost' => ($input['service'] ?? 'standard') === 'express' ? 45.0 : 25.0, 'carrier' => 'Demo Carrier'];
    }

    public function track(Shipment $shipment, array $config): array
    {
        $next = self::NEXT[$shipment->status] ?? null;

        return $next ? [new TrackingEvent($next, $shipment->tracking_number, $shipment->reference, 'Update from the demo carrier', 'Demo hub', now())] : [];
    }

    public function parseWebhook(Request $request, array $config): ?TrackingEvent
    {
        throw new \App\Services\Payment\InvalidWebhook('The demo carrier has no webhook.');
    }

    public function test(array $config): array
    {
        return ['ok' => true, 'message' => 'The demo carrier is always available.'];
    }
}
