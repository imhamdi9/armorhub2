<?php

namespace App\Services\Payment\Providers;

use App\Models\Order;
use App\Services\Payment\InvalidWebhook;
use App\Services\Payment\PaymentEvent;
use App\Services\Payment\PaymentProvider;
use App\Services\Payment\PaymentUnavailable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Charges nobody. For development, demos and tests; refused in production unless allowed. */
class SimulatedProvider implements PaymentProvider
{
    public function key(): string
    {
        return 'simulated';
    }

    public function label(): string
    {
        return 'Simulated (demo, no money moves)';
    }

    public function help(): string
    {
        return 'Marks orders paid instantly without charging anyone. Use only for demos and testing.';
    }

    public function fields(): array
    {
        return [
            'allow_in_production' => [
                'label' => 'Allow in production',
                'type' => 'bool',
                'default' => fn () => (bool) config('platform.allow_simulated_payments_in_production'),
                'help' => 'Leave off for a real launch: in production the simulated provider is refused unless this is on.',
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

        return ['status' => 'completed', 'transaction_id' => 'SIM-'.Str::upper(Str::random(12))];
    }

    public function parseWebhook(Request $request, array $config): ?PaymentEvent
    {
        throw new InvalidWebhook('The simulated provider has no webhooks.');
    }

    public function test(array $config): array
    {
        return ['ok' => true, 'message' => 'Nothing to check: no money moves with this provider.'];
    }
}
