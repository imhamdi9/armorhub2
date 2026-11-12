<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\Settings\Settings;
use Closure;
use Illuminate\Http\Request;

/**
 * Every signed-in request runs in the settings of the person's own market, so a payment, a shipment or a fee follows THEIR market's configuration
 * (its own provider and keys when it has them, the platform-wide ones otherwise). Platform admins work platform-wide, or inside one market
 * when they ask for it with ?market=<id>.
 *
 * `market.own` is stricter: the person (a market manager) can only ever see and change their own market's settings, never the platform-wide ones.
 */
class ScopeMarketSettings
{
    public function handle(Request $request, Closure $next, string $mode = 'auto')
    {
        $settings = app(Settings::class);
        $user = $request->user();
        $own = $user?->company?->tenant_id;

        if ($mode === 'own') {
            abort_unless($own && $user->can('market.settings'), 403, 'You do not manage a market.');
            $settings->enterMarket((int) $own);
        } elseif ($user?->hasRole('platform-admin')) {
            $asked = $request->integer('market') ?: null;
            $settings->enterMarket($asked && Tenant::whereKey($asked)->exists() ? $asked : null);
        } else {
            $settings->enterMarket($own ? (int) $own : null);
        }

        try {
            return $next($request);
        } finally {
            $settings->enterMarket(null);
        }
    }
}
