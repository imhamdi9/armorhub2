<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Tenant;
use App\Support\Markets\Markets;
use Illuminate\Http\Request;

/** What a market manager sees about their own market. */
class MarketSelfController extends Controller
{
    public function show(Request $request, Markets $markets)
    {
        $tenant = Tenant::findOrFail($request->attributes->get('market_tenant_id') ?? $request->user()->company->tenant_id);
        $store = Company::withoutGlobalScopes()->where('tenant_id', $tenant->id)->stores()->whereNotNull('slug')->orderBy('id')->first();
        $m = $markets->get($tenant->market);

        return [
            'id' => $tenant->id, 'name' => $tenant->name, 'country' => $tenant->market, 'currency' => $m?->currency(), 'status' => $tenant->status, 'store_slug' => $store?->slug,
            // Each market with its own keys has its own webhook addresses, so its provider can reach it.
            'webhook_urls' => ['payments' => url("/api/webhooks/{provider}/{$tenant->id}"), 'shipping' => url("/api/webhooks/shipping/{provider}/{$tenant->id}")],
        ];
    }
}
