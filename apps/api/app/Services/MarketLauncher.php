<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Industry;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Audit;
use App\Support\Markets\Markets;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Opens an ArmorHub market in a country: ArmorHub Egypt, ArmorHub Brazil... One market per country, running any set of industries,
 * with one public store. Sellers, centres and customers then join that market; nobody trades across markets except by joining the other.
 */
class MarketLauncher
{
    public function __construct(private Markets $markets)
    {
    }

    /** @param list<int> $industryIds */
    public function open(string $country, string $name, array $industryIds, ?string $slug, ?User $by = null, array $switches = []): Tenant
    {
        $country = strtoupper($country);

        if (! $this->markets->get($country)) {
            throw ValidationException::withMessages(['country' => ['Unknown country.']]);
        }
        if (Tenant::where('market', $country)->exists()) {
            throw ValidationException::withMessages(['country' => [\App\Support\PlatformBrand::in('ArmorHub already has a market in this country. Add industries to it instead.')]]);
        }
        $industries = Industry::whereIn('id', $industryIds)->pluck('id')->all();
        if (count($industries) === 0) {
            throw ValidationException::withMessages(['industries' => ['Choose at least one industry for the market.']]);
        }

        return DB::transaction(function () use ($country, $name, $industries, $slug, $by, $switches) {
            $tenant = Tenant::create(['uuid' => (string) Str::uuid(), 'name' => $name, 'market' => $country, 'status' => 'active', 'industry_id' => null] + Arr::only($switches, ['selling_enabled', 'installation_enabled']) + (isset($switches['name_ar']) && trim((string) $switches['name_ar']) !== '' ? ['settings' => ['name_ar' => trim((string) $switches['name_ar'])]] : []));
            $tenant->industries()->sync($industries);

            $company = Company::create([
                'tenant_id' => $tenant->id, 'name' => $name, 'status' => 'active', 'slug' => $slug ? Company::uniqueSlug($slug) : Company::uniqueSlug('armorhub-'.strtolower($country)),
                'settings' => ['storefront' => ['enabled' => true, 'headline' => $name, 'primary_color' => '#111827']],
            ]);
            Audit::record('market.opened', $tenant, ['country' => $country, 'name' => $name, 'industries' => $industries, 'store' => $company->slug] + $switches, $by);

            return $tenant;
        });
    }

    /** @param list<int> $industryIds */
    public function update(Tenant $tenant, ?string $name, ?array $industryIds, ?string $status, ?User $by = null, array $switches = []): Tenant
    {
        return DB::transaction(function () use ($tenant, $name, $industryIds, $status, $by, $switches) {
            if ($name !== null) {
                $tenant->update(['name' => $name]);
                Company::withoutGlobalScopes()->where('tenant_id', $tenant->id)->stores()->whereNotNull('slug')->update(['name' => $name]);
            }
            if ($status !== null) {
                $tenant->update(['status' => $status]);
            }
            if ($switches !== []) {
                $tenant->update(Arr::only($switches, ['selling_enabled', 'installation_enabled']));
                if (array_key_exists('name_ar', $switches)) {
                    $settings = (array) $tenant->settings;
                    trim((string) $switches['name_ar']) === '' ? Arr::forget($settings, 'name_ar') : $settings['name_ar'] = trim((string) $switches['name_ar']);
                    $tenant->update(['settings' => $settings]);
                }
            }
            if ($industryIds !== null) {
                $keep = Industry::whereIn('id', $industryIds)->pluck('id')->all();
                if (count($keep) === 0) {
                    throw ValidationException::withMessages(['industries' => ['A market needs at least one industry.']]);
                }
                $tenant->industries()->sync($keep);
            }
            Audit::record('market.updated', $tenant, ['name' => $name, 'status' => $status, 'industries' => $industryIds] + $switches, $by);

            return $tenant->fresh();
        });
    }

    public function storeOf(Tenant $tenant): ?Company
    {
        return Company::withoutGlobalScopes()->where('tenant_id', $tenant->id)->stores()->whereNotNull('slug')->orderBy('id')->first();
    }
}
