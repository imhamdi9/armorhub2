<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Services\Payment\InvalidWebhook;
use App\Services\Shipping\ShipmentService;
use App\Services\Shipping\ShippingProviders;
use App\Support\Audit;
use Illuminate\Http\Request;

/** /api/webhooks/shipping/{provider}: signed tracking updates from a carrier. */
class ShippingWebhookController extends Controller
{
    public function handle(Request $request, string $provider, ShippingProviders $providers, ShipmentService $shipments)
    {
        abort_unless($providers->has($provider), 404);

        try {
            $event = $providers->get($provider)->parseWebhook($request, $providers->config($provider));
        } catch (InvalidWebhook $e) {
            Audit::record('shipping.webhook_rejected', null, ['provider' => $provider, 'reason' => $e->getMessage()]);

            return response()->json(['message' => 'Invalid webhook'], 400);
        }

        if ($event) {
            $shipment = Shipment::where('provider', $provider)
                ->where(fn ($q) => $q->when($event->trackingNumber, fn ($w) => $w->orWhere('tracking_number', $event->trackingNumber))
                    ->when($event->reference, fn ($w) => $w->orWhere('reference', $event->reference)))
                ->first();

            // Unknown parcels are acknowledged (not retried forever) but change nothing.
            if ($shipment) {
                $shipments->apply($shipment, $event);
            }
        }

        return response()->json(['received' => true]);
    }
}
