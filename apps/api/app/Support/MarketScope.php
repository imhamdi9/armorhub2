<?php

namespace App\Support;

use App\Models\Company;
use App\Models\User;

/** What a market admin may reach: everything of their own market (all its companies), nothing of another. Platform admins reach all. */
class MarketScope
{
    /** @return array<int>|null company ids of the person's market, or null when unrestricted (platform admin). */
    public static function companyIds(?User $user): ?array
    {
        if (! $user || $user->hasRole('platform-admin')) {
            return null;
        }
        $tenant = $user->company?->tenant_id;

        return $tenant ? Company::withoutGlobalScopes()->where('tenant_id', $tenant)->pluck('id')->all() : [(int) $user->company_id];
    }

    public static function tenantId(?User $user): ?int
    {
        return $user?->company?->tenant_id;
    }
}
