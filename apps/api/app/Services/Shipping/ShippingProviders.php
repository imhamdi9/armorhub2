<?php

namespace App\Services\Shipping;

use App\Support\Settings\Definition;
use App\Support\Settings\Settings;
use App\Support\Settings\SettingsRegistry;
use InvalidArgumentException;

/** The installed carrier drivers and their runtime configuration (`shipping.<provider>.<field>`). */
class ShippingProviders
{
    /** @var array<string, ShippingProvider> */
    private array $providers = [];

    public function __construct(private SettingsRegistry $registry, private Settings $settings)
    {
    }

    public function register(ShippingProvider $provider): void
    {
        $this->providers[$provider->key()] = $provider;

        foreach ($provider->fields() as $field => $spec) {
            $this->registry->register(new Definition(
                key: "shipping.{$provider->key()}.{$field}", group: 'shipping', label: $spec['label'], type: $spec['type'] ?? 'string',
                default: $spec['default'] ?? null, help: $spec['help'] ?? '', options: $spec['options'] ?? [], rules: $spec['rules'] ?? [],
            ));
        }

        $this->registry->register(new Definition(
            key: 'shipping.active_provider', group: 'shipping', label: 'Default shipping provider', type: 'select',
            default: 'manual', options: array_map(fn (ShippingProvider $p) => $p->label(), $this->providers),
        ));
    }

    /** @return array<string, ShippingProvider> */
    public function all(): array
    {
        return $this->providers;
    }

    public function get(string $key): ShippingProvider
    {
        return $this->providers[$key] ?? throw new InvalidArgumentException("Unknown shipping provider [{$key}].");
    }

    public function has(string $key): bool
    {
        return isset($this->providers[$key]);
    }

    public function activeKey(): string
    {
        $key = (string) $this->settings->get('shipping.active_provider');

        return $this->has($key) ? $key : 'manual';
    }

    /** @return array<string, mixed> */
    public function config(string $key): array
    {
        $values = [];

        foreach (array_keys($this->get($key)->fields()) as $field) {
            $values[$field] = $this->settings->get("shipping.{$key}.{$field}");
        }

        return $values;
    }

    public function isConfigured(string $key): bool
    {
        return $this->get($key)->isConfigured($this->config($key));
    }
}
