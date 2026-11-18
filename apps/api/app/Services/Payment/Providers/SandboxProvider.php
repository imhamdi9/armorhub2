<?php

namespace App\Services\Payment\Providers;

use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\SandboxCharge;
use App\Services\Payment\InvalidWebhook;
use App\Services\Payment\PaymentEvent;
use App\Services\Payment\PaymentProvider;
use App\Services\Payment\PaymentUnavailable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * A payment provider that behaves like a hosted card page, with test cards instead of real ones: the customer is sent to a checkout page, enters a
 * (test) card or wallet code, and is sent back. The whole redirect, decline, retry and confirmation path is exercised without any credentials or money.
 * Refused in production unless allowed.
 */
class SandboxProvider implements PaymentProvider
{
    public function key(): string
    {
        return 'sandbox';
    }

    public function label(): string
    {
        return 'Sandbox checkout (test cards, no real money)';
    }

    public function help(): string
    {
        return 'Sends the customer to a hosted test checkout page. Test card 4242 4242 4242 4242 is approved; 4000 0000 0000 0002 is declined. For demos and testing only.';
    }

    public function fields(): array
    {
        return [
            'allow_in_production' => [
                'label' => 'Allow in production',
                'type' => 'bool',
                'default' => false,
                'help' => 'Leave off for a real launch: in production the sandbox is refused unless this is on.',
            ],
        ];
    }

    public function isConfigured(array $config): bool
    {
        return ! app()->isProduction() || (bool) ($config['allow_in_production'] ?? false);
    }

    public function charge(Order $order, float $amount, ?string $method, array $config): array
    {
        if (! $this->isConfigured($config)) {
            throw new PaymentUnavailable('Online payments are not configured yet.');
        }
        $web = rtrim((string) config('platform.web_url'), '/');
        $id = (int) $order->id;
        $back = match (true) {
            OrderGroup::fromPaymentId($id) !== null => "{$web}/dashboard/purchases/".OrderGroup::fromPaymentId($id),
            $id < 0 => "{$web}/dashboard/installations/".(-$id),
            default => "{$web}/dashboard/orders/{$id}",
        };
        $charge = SandboxCharge::create(['ref' => 'SBX-'.Str::upper(Str::random(40)), 'payment_id' => $id, 'amount' => $amount, 'currency' => strtoupper((string) $order->currency), 'return_url' => $back]);

        return ['status' => 'redirect', 'transaction_id' => $charge->ref, 'url' => "{$web}/pay/sandbox/{$charge->ref}"];
    }

    public function parseWebhook(Request $request, array $config): ?PaymentEvent
    {
        throw new InvalidWebhook('The sandbox confirms payments on its own checkout page, not by webhook.');
    }

    public function test(array $config): array
    {
        return ['ok' => true, 'message' => 'Nothing to check: the sandbox needs no credentials and moves no money.'];
    }

    /** Sending money back is only a record here. @return array{ok: bool, reference: string, message: string} */
    public function refund(string $transactionId, float $amount, string $currency, array $config): array
    {
        return ['ok' => true, 'reference' => 'SBX-RF-'.Str::upper(Str::random(10)), 'message' => 'The test refund was recorded.'];
    }
}
