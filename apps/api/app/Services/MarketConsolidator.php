<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Early ArmorHub had one "market" per country AND industry (Pharaoh Auto, Beit Masr...). Now each country has ONE market running all its
 * industries. This folds the older ones into the oldest market of the country: their industries, people, orders and sellers move over,
 * and the old store addresses forward to the market's store. Safe to run again.
 */
class MarketConsolidator
{
    /** @return int how many markets were folded into another */
    public function run(): int
    {
        $folded = 0;

        foreach (Tenant::whereNotNull('market')->where('status', '!=', 'merged')->get()->groupBy('market') as $tenants) {
            if ($tenants->count() > 1) {
                $folded += $this->fold($tenants->sortBy('id')->values());
            }
        }

        return $folded;
    }

    private function fold($tenants): int
    {
        $primary = $tenants->first();
        $primaryStore = Company::withoutGlobalScopes()->where('tenant_id', $primary->id)->stores()->whereNotNull('slug')->orderBy('id')->first();
        $n = 0;

        foreach ($tenants->slice(1) as $other) {
            DB::transaction(function () use ($primary, $primaryStore, $other) {
                $primary->industries()->syncWithoutDetaching($other->industryIds());

                $oldStores = Company::withoutGlobalScopes()->where('tenant_id', $other->id)->stores()->whereNotNull('slug')->get();
                if ($primaryStore) {
                    // Everything that pointed at an old store (its people, orders, quotes, payments) now points at the market's store.
                    foreach (Schema::getTables() as $table) {
                        if (in_array($table['name'], ['companies'], true) || ! Schema::hasColumn($table['name'], 'company_id')) {
                            continue;
                        }
                        DB::table($table['name'])->whereIn('company_id', $oldStores->pluck('id'))->update(['company_id' => $primaryStore->id]);
                    }
                    foreach ($oldStores as $store) {
                        $settings = $store->settings ?? [];
                        $settings['storefront']['enabled'] = false;
                        $settings['merged_into'] = $primaryStore->slug;
                        $store->update(['settings' => $settings, 'status' => 'inactive']);
                    }
                }

                // Sellers, centres and anything else of the old market now belong to the country's market.
                Company::withoutGlobalScopes()->where('tenant_id', $other->id)->update(['tenant_id' => $primary->id]);
                $other->update(['status' => 'merged']);
            });
            $n++;
        }

        return $n;
    }
}
