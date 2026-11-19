<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdConversionLog;
use App\Models\AdSpend;
use App\Models\Company;
use App\Models\Order;
use App\Models\PartnerApplication;
use App\Services\Marketing\AdConversions;
use App\Services\Marketing\AdSpendSync;
use App\Support\Settings\Settings;
use Illuminate\Http\Request;

/** What the ads brought: orders and seller or centre applications by campaign, and what was reported to the ad platforms. */
class MarketingController extends Controller
{
    /** The platform and campaign a visit belongs to: the utm tags when there are, else what the click id says. */
    private function key(?array $a): array
    {
        if (! $a) {
            return ['direct / not tracked', '-'];
        }
        $source = $a['utm_source'] ?? (isset($a['fbclid']) ? 'facebook' : (isset($a['ttclid']) ? 'tiktok' : (isset($a['gclid']) ? 'google' : 'campaign')));

        // "Eid Sale" and "eid-sale" are one campaign.
        return [mb_strtolower($source), ($c = AdSpend::key((string) ($a['utm_campaign'] ?? ''))) !== '' ? $c : '-'];
    }

    public function attribution(Request $request, Settings $settings)
    {
        $days = min(365, max(1, (int) $request->query('days', 30)));
        $market = $settings->market();
        $since = now()->subDays($days);

        $orders = Order::withoutGlobalScopes()->where('created_at', '>=', $since)
            ->when($market, fn ($q) => $q->whereIn('company_id', Company::withoutGlobalScopes()->where('tenant_id', $market)->select('id')))
            ->whereNotIn('status', ['pending', 'cancelled'])->latest('id')->limit(5000)->get();   // whole rows: the payable amount is worked out from several of them
        $apps = PartnerApplication::where('created_at', '>=', $since)->when($market, fn ($q) => $q->where('tenant_id', $market))->latest('id')->limit(5000)->get(['id', 'type', 'attribution']);

        $rows = [];
        foreach ($orders as $o) {
            [$s, $c] = $this->key($o->attribution);
            $rows["$s|$c"] ??= ['source' => $s, 'campaign' => $c, 'orders' => 0, 'revenue' => 0.0, 'sellers' => 0, 'centres' => 0, 'currency' => $o->currency];
            $rows["$s|$c"]['orders']++;
            // What the customer really paid: a refunded order brings nothing, a partly refunded one its remainder.
            $net = $o->refund_status ? 0.0 : (float) $o->payable_amount - (float) ($o->partial_refund_status ? $o->partial_refund_amount : 0);
            $rows["$s|$c"]['revenue'] += max(0.0, $net);
        }
        foreach ($apps as $a) {
            [$s, $c] = $this->key($a->attribution);
            $rows["$s|$c"] ??= ['source' => $s, 'campaign' => $c, 'orders' => 0, 'revenue' => 0.0, 'sellers' => 0, 'centres' => 0, 'currency' => null];
            $a->type === 'seller' ? $rows["$s|$c"]['sellers']++ : $rows["$s|$c"]['centres']++;
        }

        $rows = $this->withSpend(array_values($rows), $since, $market);

        return [
            'days' => $days,
            'rows' => collect($rows)->sortByDesc(fn ($r) => [$r['revenue'], $r['spend']])->values()->all(),
            'totals' => [
                'orders' => $orders->count(), 'tracked_orders' => $orders->whereNotNull('attribution')->count(),
                'applications' => $apps->count(), 'tracked_applications' => $apps->whereNotNull('attribution')->count(),
                'spend' => round(collect($rows)->sum('spend'), 2),
            ],
            'spend_synced_at' => AdSpend::max('updated_at'),
        ];
    }

    private const SOURCE_PLATFORM = ['facebook' => 'meta', 'instagram' => 'meta', 'meta' => 'meta', 'fb' => 'meta', 'ig' => 'meta', 'tiktok' => 'tiktok'];

    /**
     * Puts what the ad accounts spent next to what each campaign brought. A market with an account of its own sees that account;
     * a market without one sees the platform's (shared, so it cannot be split by market). The platform view sees every account.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function withSpend(array $rows, $since, ?int $market): array
    {
        $spend = AdSpend::where('day', '>=', $since->toDateString())->get();
        $shared = collect();                                   // platforms whose account this market shares with the platform
        if ($market) {
            $own = $spend->where('owner_tenant_id', $market)->pluck('platform')->unique();
            $shared = $spend->whereNull('owner_tenant_id')->pluck('platform')->unique()->diff($own);
            $spend = $spend->filter(fn ($r) => $r->owner_tenant_id === $market || ($r->owner_tenant_id === null && ! $own->contains($r->platform)));
        }
        $byKey = $spend->groupBy(fn ($r) => $r->platform.'|'.$r->campaign_key)->map(fn ($g) => [
            'spend' => round($g->sum('spend'), 2), 'impressions' => (int) $g->sum('impressions'), 'clicks' => (int) $g->sum('clicks'), 'currency' => $g->first()->currency,
            'platform' => $g->first()->platform, 'name' => $g->first()->campaign_name, 'key' => $g->first()->campaign_key,
        ]);

        $blank = ['spend' => 0.0, 'spend_currency' => null, 'impressions' => 0, 'clicks' => 0, 'cost_per_order' => null, 'cost_per_application' => null, 'roas' => null, 'shared_account' => false];
        $used = [];
        foreach ($rows as &$r) {
            $r += $blank;
            $platform = self::SOURCE_PLATFORM[mb_strtolower($r['source'])] ?? null;
            $hit = $platform ? $byKey->get($platform.'|'.AdSpend::key((string) $r['campaign'])) : null;
            if ($hit) {
                $used[$platform.'|'.$hit['key']] = true;
                $r = $this->attach($r, $hit, $shared->contains($platform));
            }
        }
        unset($r);
        foreach ($byKey as $k => $hit) {                       // a campaign that spent but has no orders yet still shows
            if (! isset($used[$k])) {
                $rows[] = $this->attach(['source' => $hit['platform'], 'campaign' => $hit['key'], 'orders' => 0, 'revenue' => 0.0, 'sellers' => 0, 'centres' => 0, 'currency' => null] + $blank, $hit, $shared->contains($hit['platform']));
            }
        }

        return $rows;
    }

    /** @param array<string, mixed> $r @param array<string, mixed> $hit */
    private function attach(array $r, array $hit, bool $shared): array
    {
        $r['spend'] = $hit['spend'];
        $r['spend_currency'] = $hit['currency'];
        $r['impressions'] = $hit['impressions'];
        $r['clicks'] = $hit['clicks'];
        $r['shared_account'] = $shared;
        $sameMoney = ! $r['currency'] || ! $hit['currency'] || $r['currency'] === $hit['currency'];
        $r['cost_per_order'] = $r['orders'] > 0 && $sameMoney && $hit['spend'] > 0 ? round($hit['spend'] / $r['orders'], 2) : null;
        $signups = (int) $r['sellers'] + (int) $r['centres'];
        $r['cost_per_application'] = $signups > 0 && $hit['spend'] > 0 ? round($hit['spend'] / $signups, 2) : null;
        $r['roas'] = $hit['spend'] > 0 && $r['revenue'] > 0 && $sameMoney ? round($r['revenue'] / $hit['spend'], 2) : null;

        return $r;
    }

    /** Reads the spend again from the ad platforms now (read only). */
    public function syncSpend(Settings $settings, AdSpendSync $sync)
    {
        return ['results' => $sync->run(30, $settings->market())];
    }

    public function logs(Request $request, Settings $settings)
    {
        return AdConversionLog::query()->when($settings->market(), fn ($q, $m) => $q->where('tenant_id', $m))
            ->when($request->query('platform'), fn ($q, $p) => $q->where('platform', $p))->latest('id')->paginate(30);
    }

    /** The markets a person can aim ads at (the marketing team has no other way to list them). */
    public function markets()
    {
        return \App\Models\Tenant::whereNotNull('market')->orderBy('name')->get(['id', 'name', 'market'])->all();
    }

    /** Only the marketing settings: the marketing team never sees or changes any other setting. */
    public function settings(Settings $settings)
    {
        return ['settings' => $settings->describe('marketing')];
    }

    public function saveSettings(Request $request, Settings $settings)
    {
        $values = (array) $request->input('values', []);
        abort_unless($values !== [] && collect($values)->keys()->every(fn ($k) => str_starts_with((string) $k, 'marketing.')), 403, 'Only marketing settings can be changed here.');
        app(SettingsController::class)->update($request, $settings);

        return ['settings' => $settings->describe('marketing')];
    }

    public function resetSetting(string $key, Settings $settings)
    {
        abort_unless(str_starts_with($key, 'marketing.'), 403, 'Only marketing settings can be changed here.');
        $settings->forget($key);

        return ['settings' => $settings->describe('marketing')];
    }

    /** Sends one made-up purchase to the platforms the market has switched on (they should be in test mode), so the admin can see it arrive. */
    public function test(Settings $settings, AdConversions $conversions)
    {
        return ['results' => $conversions->test($settings->market())];
    }
}
