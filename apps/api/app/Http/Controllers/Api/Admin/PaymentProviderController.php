<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payment\PaymentProviders;
use App\Support\Audit;
use App\Support\Settings\Settings;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PaymentProviderController extends Controller
{
    public function index(PaymentProviders $providers, Settings $settings)
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
                    'value' => ($spec['type'] ?? 'string') === 'secret' ? null : $settings->get("payments.{$key}.{$name}"),
                    'has_value' => ($spec['type'] ?? 'string') === 'secret' ? filled($settings->get("payments.{$key}.{$name}")) : null,
                ])->values();

                return [
                    'key' => $key,
                    'label' => $provider->label(),
                    'help' => $provider->help(),
                    'active' => $key === $active,
                    'configured' => $providers->isConfigured($key),
                    'webhook_url' => "{$api}/api/webhooks/{$key}",
                    'fields' => $fields,
                ];
            })->values(),
        ];
    }

    /** Save a provider's credentials (blank secrets mean "leave unchanged"). */
    public function update(Request $request, PaymentProviders $providers, Settings $settings, string $key)
    {
        abort_unless($providers->has($key), 404);
        $values = $request->validate(['values' => 'required|array'])['values'];

        foreach ($values as $field => $value) {
            $name = "payments.{$key}.{$field}";

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

    /** Make a provider the one new payments go through. It must be fully configured first. */
    public function activate(Request $request, PaymentProviders $providers, Settings $settings)
    {
        $key = $request->validate(['provider' => 'required|string'])['provider'];
        abort_unless($providers->has($key), 404);

        if (! $providers->isConfigured($key)) {
            throw ValidationException::withMessages(['provider' => ['Finish configuring this provider before activating it.']]);
        }

        $previous = $providers->activeKey();
        $settings->set('payments.active_provider', $key, $request->user());
        Audit::record('payments.provider_activated', null, ['from' => $previous, 'to' => $key]);

        return $this->index($providers, $settings);
    }

    public function test(PaymentProviders $providers, string $key)
    {
        abort_unless($providers->has($key), 404);

        return $providers->get($key)->test($providers->config($key));
    }

    /** Recent payments across all providers, for monitoring. */
    public function payments(Request $request)
    {
        $market = app(Settings::class)->market();

        return Payment::withoutGlobalScopes()->latest('id')
            ->when($market, fn ($q) => $q->whereIn('company_id', \App\Models\Company::withoutGlobalScopes()->where('tenant_id', $market)->select('id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->paginate(30)
            ->through(fn (Payment $p) => [
                'id' => $p->id, 'order_id' => $p->order_id, 'amount' => $p->amount, 'currency' => $p->currency,
                'status' => $p->status, 'provider' => $p->provider, 'reference' => $p->transaction_id, 'created_at' => $p->created_at,
            ]);
    }
}
