<?php

namespace App\Services\Shipping\Providers;

use App\Models\Order;
use App\Models\Shipment;
use App\Services\Shipping\ShippingProvider;
use App\Services\Shipping\ShippingUnavailable;
use App\Services\Shipping\TrackingEvent;
use Illuminate\Http\Request;

/**
 * Works with ANY carrier on day one: staff book the parcel with the carrier themselves and record the
 * carrier name and tracking number here. Customers get a tracking link, and staff move the status along.
 */
class ManualProvider implements ShippingProvider
{
    public function key(): string
    {
        return 'manual';
    }

    public function label(): string
    {
        return 'Manual (any carrier)';
    }

    public function help(): string
    {
        return 'No connection needed. Staff enter the carrier and tracking number after booking the parcel. Add a tracking link template so customers can click through, e.g. https://track.example.com/?n={tracking}.';
    }

    public function fields(): array
    {
        return [
            'tracking_url_template' => ['label' => 'Tracking link template (https)', 'type' => 'string',
                'help' => 'Use {tracking} where the tracking number goes. Optional.', 'rules' => ['nullable', 'regex:~^https://[^\s<>"\']+$~i', 'max:500']],
        ];
    }

    public function isConfigured(array $config): bool
    {
        return true;
    }

    public function quote(Order $order, array $address, array $config): array
    {
        return [];
    }

    public function create(Order $order, array $address, array $input, array $config): array
    {
        $carrier = trim((string) ($input['carrier'] ?? ''));
        $tracking = trim((string) ($input['tracking_number'] ?? ''));

        if ($carrier === '' || $tracking === '') {
            throw new ShippingUnavailable('Enter the carrier name and the tracking number.');
        }

        $template = (string) ($config['tracking_url_template'] ?? '');

        return [
            'reference' => null,
            'tracking_number' => $tracking,
            'tracking_url' => $template !== '' ? str_replace('{tracking}', rawurlencode($tracking), $template) : null,
            'label_url' => null,
            'cost' => isset($input['cost']) ? (float) $input['cost'] : null,
            'carrier' => $carrier,
        ];
    }

    public function track(Shipment $shipment, array $config): array
    {
        return [];
    }

    public function parseWebhook(Request $request, array $config): ?TrackingEvent
    {
        throw new \App\Services\Payment\InvalidWebhook('Manual shipments have no webhook.');
    }

    public function test(array $config): array
    {
        return ['ok' => true, 'message' => 'Nothing to connect: staff enter tracking numbers by hand.'];
    }
}
