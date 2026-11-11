<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Shipping\ShippingProviders;
use App\Support\Audit;
use App\Support\Settings\Settings;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ShippingProviderController extends Controller
{
    public function index(ShippingProviders $providers, Settings $settings)
    {
        $active = $providers->activeKey();
        $api = rtrim((string) config('app.url'), '/');

        return [
            'active' => $active,
            'providers' => collect($providers->all())->map(function ($provider) use ($providers, $settings, $active, $api) {
                $key = $provider->key();
                $fields = collect($provider->fields())->map(fn ($spec, $name) => [
                    'key' => $name,
                    'label' => $spec['label'],
                    'type' => $spec['type'] ?? 'string',
                    'required' => $spec['required'] ?? false,
                    'help' => $spec['help'] ?? '',
                    'options' => $spec['options'] ?? [],
                    // Secrets are never sent back, only whether one is set.
                    'value' => ($spec['type'] ?? 'string') === 'secret' ? null : $settings->get("shipping.{$key}.{$name}"),
                    'has_value' => ($spec['type'] ?? 'string') === 'secret' ? filled($settings->get("shipping.{$key}.{$name}")) : null,
                ])->values();

                return [
                    'key' => $key,
                    'label' => $provider->label(),
                    'help' => $provider->help(),
                    'active' => $key === $active,
                    'configured' => $providers->isConfigured($key),
                    'webhook_url' => "{$api}/api/webhooks/shipping/{$key}",
                    'fields' => $fields,
                ];
            })->values(),
        ];
    }

    /** Save a provider's credentials (blank secrets mean "leave unchanged"). */
    public function update(Request $request, ShippingProviders $providers, Settings $settings, string $key)
    {
        abort_unless($providers->has($key), 404);
        $values = $request->validate(['values' => 'required|array'])['values'];

        foreach ($values as $field => $value) {
            $name = "shipping.{$key}.{$field}";

            if (! $settings->registry()->has($name)) {
                throw ValidationException::withMessages(["values.{$field}" => ['Unknown field.']]);
            }

            $definition = $settings->registry()->get($name);
            $validator = \Illuminate\Support\Facades\Validator::make(['value' => $value], ['value' => $definition->validationRules()]);

            if ($validator->fails()) {
                throw ValidationException::withMessages(["values.{$field}" => $validator->errors()->get('value')]);
            }

            if ($definition->isSecret() && ($value === null || $value === '' || $value === Settings::MASK)) {
                continue;
            }

            $settings->set($name, $value, $request->user());
        }

        return $this->index($providers, $settings);
    }

    /** Make a provider the default for new shipments. It must be fully configured first. */
    public function activate(Request $request, ShippingProviders $providers, Settings $settings)
    {
        $key = $request->validate(['provider' => 'required|string'])['provider'];
        abort_unless($providers->has($key), 404);

        if (! $providers->isConfigured($key)) {
            throw ValidationException::withMessages(['provider' => ['Finish configuring this provider before making it the default.']]);
        }

        $previous = $providers->activeKey();
        $settings->set('shipping.active_provider', $key, $request->user());
        Audit::record('shipping.provider_activated', null, ['from' => $previous, 'to' => $key]);

        return $this->index($providers, $settings);
    }

    public function test(ShippingProviders $providers, string $key)
    {
        abort_unless($providers->has($key), 404);

        return $providers->get($key)->test($providers->config($key));
    }
}
