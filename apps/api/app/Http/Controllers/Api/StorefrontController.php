<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\Storefront\StorefrontService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Where a company owner (or an admin) edits what the public sees of their store. */
class StorefrontController extends Controller
{
    use AuthorizesRequests;

    public function show(Company $company, StorefrontService $stores)
    {
        $this->authorize('update', $company);

        return $this->present($company, $stores);
    }

    public function update(Request $request, Company $company, StorefrontService $stores)
    {
        $this->authorize('update', $company);

        $data = $request->validate([
            'slug' => ['sometimes', 'string', 'min:3', 'max:60', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                Rule::unique('companies', 'slug')->ignore($company->id), Rule::notIn(Company::RESERVED_SLUGS)],
            'enabled' => 'sometimes|boolean',
            'headline' => 'nullable|string|max:120',
            'subheadline' => 'nullable|string|max:240',
            'about' => 'nullable|string|max:2000',
            // A strict colour: it is placed in a style attribute, so nothing else may get through.
            'primary_color' => ['sometimes', 'regex:/^#[0-9a-fA-F]{6}$/'],
            // Only https images; they are loaded by every visitor's browser.
            'logo_url' => ['nullable', 'string', 'max:500', 'url:https'],
            'hero_image_url' => ['nullable', 'string', 'max:500', 'url:https'],
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'featured_product_ids' => 'sometimes|array|max:8',
            'featured_product_ids.*' => 'integer',
        ]);

        // Featured products must be ones this store can actually show.
        if (isset($data['featured_product_ids'])) {
            $valid = $stores->products($company)->whereIn('id', $data['featured_product_ids'])->pluck('id')->all();

            if (count($valid) !== count(array_unique($data['featured_product_ids']))) {
                throw ValidationException::withMessages(['featured_product_ids' => ['Some selected products are not available in this store.']]);
            }

            $data['featured_product_ids'] = array_values($valid);
        }

        if (array_key_exists('slug', $data)) {
            $company->slug = $data['slug'];
            unset($data['slug']);
        }

        $settings = $company->settings ?? [];
        $settings['storefront'] = array_merge($stores->settingsOf($company), $data);
        $company->settings = $settings;
        $company->save();

        return $this->present($company->fresh(), $stores);
    }

    private function present(Company $company, StorefrontService $stores): array
    {
        return ['slug' => $company->slug, 'path' => "/s/{$company->slug}"] + $stores->settingsOf($company);
    }
}
