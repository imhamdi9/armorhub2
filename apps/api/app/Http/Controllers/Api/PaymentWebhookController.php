<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Payment\InvalidWebhook;
use App\Services\Payment\PaymentProviders;
use App\Services\Payment\PaymentWebhookHandler;
use App\Support\Audit;
use Illuminate\Http\Request;

/**
 * One webhook URL per provider: /api/webhooks/{provider}. Any configured provider is accepted,
 * not only the active one, so payments already started with a previous provider still complete
 * after an admin switches.
 */
class PaymentWebhookController extends Controller
{
    public function handle(Request $request, string $provider, PaymentProviders $providers, PaymentWebhookHandler $handler, ?int $market = null)
    {
        abort_unless($providers->has($provider), 404);

        // A market with its own keys has its own address: /api/webhooks/{provider}/{market}, verified with that market's secret.
        app(\App\Support\Settings\Settings::class)->enterMarket($market);
        $config = $providers->config($provider);

        try {
            $event = $providers->get($provider)->parseWebhook($request, $config);
        } catch (InvalidWebhook $e) {
            Audit::record('payments.webhook_rejected', null, ['provider' => $provider, 'reason' => $e->getMessage()]);

            return response()->json(['message' => 'Invalid webhook'], 400);
        }

        if ($event) {
            $handler->handle($provider, $event);
        }

        // Always acknowledge events we understand or ignore, so the provider stops retrying.
        return response()->json(['received' => true]);
    }
}
