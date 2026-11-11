<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Support\Settings\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SettingsController extends Controller
{
    public function index(Settings $settings, Request $request)
    {
        return ['settings' => $settings->describe($request->query('group'))];
    }

    /** Save a batch of values. Blank secrets (and the mask) mean "leave unchanged". */
    public function update(Request $request, Settings $settings)
    {
        $registry = $settings->registry();
        $values = $request->validate(['values' => 'required|array'])['values'];

        // Setting keys contain dots, which Laravel would read as nested paths, so each value
        // is validated on its own instead of through a combined "values.<key>" rule.
        $errors = [];
        foreach ($values as $key => $value) {
            if (! $registry->has($key)) {
                $errors["values.$key"] = ['Unknown setting.'];
                continue;
            }

            $validator = Validator::make(['value' => $value], ['value' => $registry->get($key)->validationRules()]);

            if ($validator->fails()) {
                $errors["values.$key"] = $validator->errors()->get('value');
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        // The commission shares must always add up to 100%.
        $split = collect(['platform', 'company', 'partner'])->mapWithKeys(fn ($p) => [
            $p => (float) ($values["platform.commission_split.$p"] ?? $settings->get("platform.commission_split.$p")),
        ]);
        if (collect($values)->keys()->contains(fn ($k) => str_starts_with($k, 'platform.commission_split.'))
            && abs($split->sum() - 1.0) > 0.001) {
            throw ValidationException::withMessages(['values' => ['The commission shares must add up to 1.']]);
        }

        foreach ($values as $key => $value) {
            $definition = $registry->get($key);

            if ($definition->isSecret() && ($value === null || $value === '' || $value === Settings::MASK)) {
                continue;
            }

            $settings->set($key, $value, $request->user());
        }

        return ['settings' => $settings->describe($request->query('group'))];
    }

    public function reset(Request $request, Settings $settings, string $key)
    {
        abort_unless($settings->registry()->has($key), 404);

        $settings->forget($key, $request->user());

        return ['settings' => $settings->describe($request->query('group'))];
    }
}
