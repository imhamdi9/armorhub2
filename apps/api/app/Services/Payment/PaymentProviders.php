<?php

namespace App\Services\Payment;

use App\Support\Settings\Definition;
use App\Support\Settings\Settings;
use App\Support\Settings\SettingsRegistry;
use InvalidArgumentException;

/**
 * The installed provider drivers and their runtime configuration. Each driver's fields become
 * admin-editable settings (`payments.<provider>.<field>`); the active one is `payments.active_provider`.
 */
class PaymentProviders
{
    /** @var array<string, PaymentProvider> */
    private array $providers = [];

    public function __construct(private SettingsRegistry $registry, private Settings $settings)
    {
    }

    public function register(PaymentProvider $provider): void
    {
        $this->providers[$provider->key()] = $provider;

        foreach ($provider->fields() as $field => $spec) {
            $this->registry->register(new Definition(
                key: "payments.{$provider->key()}.{$field}",
                group: 'payments',
                label: $spec['label'],
                type: $spec['type'] ?? 'string',
                default: $spec['default'] ?? null,
                help: $spec['help'] ?? '',
                options: $spec['options'] ?? [],
                rules: $spec['rules'] ?? [],
            ));
        }

        $this->registry->register(new Definition(
            key: 'payments.active_provider',
            group: 'payments',
            label: 'Active payment provider',
            type: 'select',
            default: fn () => array_key_exists(config('platform.payment_gateway'), $this->providers) ? config('platform.payment_gateway') : 'simulated',
            options: array_map(fn (PaymentProvider $p) => $p->label(), $this->providers),
        ));
    }

    /** @return array<string, PaymentProvider> */
    public function all(): array
    {
        return $this->providers;
    }

    public function get(string $key): PaymentProvider
    {
        return $this->providers[$key] ?? throw new InvalidArgumentException("Unknown payment provider [{$key}].");
    }

    public function has(string $key): bool
    {
        return isset($this->providers[$key]);
    }

    public function activeKey(): string
    {
        return (string) $this->settings->get('payments.active_provider');
    }

    public function active(): PaymentProvider
    {
        return $this->get($this->activeKey());
    }

    /** Current values of a provider's fields (defaults filled in; secrets decrypted). @return array<string, mixed> */
    public function config(string $key): array
    {
        $values = [];

        foreach (array_keys($this->get($key)->fields()) as $field) {
            $values[$field] = $this->settings->get("payments.{$key}.{$field}");
        }

        return $values;
    }

    public function isConfigured(string $key): bool
    {
        return $this->get($key)->isConfigured($this->config($key));
    }
}
