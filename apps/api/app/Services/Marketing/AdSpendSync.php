<?php

namespace App\Services\Marketing;

use App\Models\AdSpend;
use App\Models\Tenant;
use App\Support\Settings\Settings;
use Illuminate\Support\Facades\Http;

/**
 * Reads what each ad account spent, per campaign and day, from Meta and TikTok (read only: nothing here can change a campaign or spend money).
 * The platform has an account of its own; a market may have its own too, and a market without one uses the platform's.
 * An account shared by several markets is read once.
 */
class AdSpendSync
{
    public function __construct(private Settings $settings)
    {
    }

    /** @return list<array{platform: string, account: string, owner: int|null, status: string, rows: int, note: string|null}> */
    public function run(int $days = 7, ?int $onlyMarket = null): array
    {
        $days = max(1, min(60, $days));
        $results = [];
        $done = [];

        foreach ([null, ...Tenant::whereNotNull('market')->pluck('id')->all()] as $tenantId) {
            if ($onlyMarket !== null && $tenantId !== $onlyMarket && $tenantId !== null) {
                continue;
            }
            $this->settings->inMarket($tenantId, function () use ($tenantId, $days, &$results, &$done) {
                foreach ($this->accounts($tenantId) as [$platform, $account, $token]) {
                    // An account is read once, for whoever owns it: the market that set it up, else the platform.
                    $owner = $tenantId !== null && $this->settings->overriddenHere('marketing.'.($platform === 'meta' ? 'meta_ad_account_id' : 'tiktok_advertiser_id')) ? $tenantId : null;
                    if (isset($done["$platform|$account"])) {
                        continue;
                    }
                    $done["$platform|$account"] = true;
                    $results[] = $this->pull($platform, $account, $token, $owner, $days);
                }
            });
        }

        return $results;
    }

    /** @return list<array{0: string, 1: string, 2: string}> */
    private function accounts(?int $tenantId): array
    {
        $s = $this->settings;
        $out = [];
        if (($id = trim((string) $s->get('marketing.meta_ad_account_id'))) !== '' && ($t = (string) $s->get('marketing.meta_ads_token')) !== '') {
            $out[] = ['meta', preg_replace('/^act_/', '', $id), $t];
        }
        if (($id = trim((string) $s->get('marketing.tiktok_advertiser_id'))) !== '' && ($t = (string) $s->get('marketing.tiktok_ads_token')) !== '') {
            $out[] = ['tiktok', $id, $t];
        }

        return $out;
    }

    private function pull(string $platform, string $account, string $token, ?int $owner, int $days): array
    {
        $since = now()->subDays($days - 1)->toDateString();
        $until = now()->toDateString();
        try {
            $rows = $platform === 'meta' ? $this->meta($account, $token, $since, $until) : $this->tiktok($account, $token, $since, $until);
            foreach ($rows as $r) {
                AdSpend::updateOrCreate(
                    ['platform' => $platform, 'account_id' => $account, 'day' => $r['day'], 'campaign_id' => $r['campaign_id']],
                    ['owner_tenant_id' => $owner, 'campaign_name' => mb_substr($r['name'], 0, 200), 'campaign_key' => AdSpend::key($r['name']), 'spend' => $r['spend'], 'currency' => $r['currency'], 'impressions' => $r['impressions'], 'clicks' => $r['clicks']],
                );
            }

            $this->checkLimit($platform, $account, $owner);

            return ['platform' => $platform, 'account' => $account, 'owner' => $owner, 'status' => 'ok', 'rows' => count($rows), 'note' => null];
        } catch (\Throwable $e) {
            AdAlerts::send('spend:'.$platform.':'.$account, 'Reading the ad spend failed', ':platform: :note', ['platform' => ucfirst($platform), 'note' => mb_substr($e->getMessage(), 0, 120)]);

            return ['platform' => $platform, 'account' => $account, 'owner' => $owner, 'status' => 'failed', 'rows' => 0, 'note' => mb_substr($e->getMessage(), 0, 250)];
        }
    }

    /** A day's spend over the limit the admin set (marketing.daily_spend_alert, per market or for the platform) tells the admins. */
    private function checkLimit(string $platform, string $account, ?int $owner): void
    {
        $limit = (float) $this->settings->get('marketing.daily_spend_alert');
        if ($limit <= 0) {
            return;
        }
        $today = (float) AdSpend::where(['platform' => $platform, 'account_id' => $account])->whereDate('day', now()->toDateString())->sum('spend');
        if ($today > $limit) {
            AdAlerts::send('limit:'.$platform.':'.$account, 'Ad spend passed the daily limit', ':platform: :note', ['platform' => ucfirst($platform), 'note' => number_format($today, 2).' > '.number_format($limit, 2)]);
        }
    }

    /** @return list<array{day: string, campaign_id: string, name: string, spend: float, currency: string|null, impressions: int, clicks: int}> */
    private function meta(string $account, string $token, string $since, string $until): array
    {
        $url = rtrim(config('marketing.endpoints.meta'), '/')."/act_{$account}/insights";
        $query = ['level' => 'campaign', 'fields' => 'campaign_id,campaign_name,spend,impressions,clicks,account_currency', 'time_increment' => 1,
            'time_range' => json_encode(['since' => $since, 'until' => $until]), 'limit' => 500, 'access_token' => $token];
        $rows = [];
        for ($page = 0; $page < 20 && $url; $page++) {
            $res = Http::timeout(config('marketing.timeout') * 2)->get($url, $page === 0 ? $query : []);
            if (! $res->successful()) {
                throw new \RuntimeException('Meta answered '.$res->status().': '.($res->json('error.message') ?? mb_substr($res->body(), 0, 150)));
            }
            foreach ($res->json('data', []) as $d) {
                $rows[] = ['day' => $d['date_start'], 'campaign_id' => (string) $d['campaign_id'], 'name' => (string) ($d['campaign_name'] ?? ''), 'spend' => (float) ($d['spend'] ?? 0),
                    'currency' => $d['account_currency'] ?? null, 'impressions' => (int) ($d['impressions'] ?? 0), 'clicks' => (int) ($d['clicks'] ?? 0)];
            }
            $url = $res->json('paging.next');
        }

        return $rows;
    }

    private function tiktok(string $advertiser, string $token, string $since, string $until): array
    {
        $rows = [];
        for ($page = 1; $page <= 20; $page++) {
            $res = Http::timeout(config('marketing.timeout') * 2)->withHeaders(['Access-Token' => $token])->get(config('marketing.endpoints.tiktok_report'), [
                'advertiser_id' => $advertiser, 'report_type' => 'BASIC', 'data_level' => 'AUCTION_CAMPAIGN', 'dimensions' => json_encode(['campaign_id', 'stat_time_day']),
                'metrics' => json_encode(['campaign_name', 'spend', 'impressions', 'clicks', 'currency']), 'start_date' => $since, 'end_date' => $until, 'page' => $page, 'page_size' => 500,
            ]);
            if (! $res->successful() || (int) $res->json('code', 0) !== 0) {
                throw new \RuntimeException('TikTok answered '.$res->status().': '.($res->json('message') ?? mb_substr($res->body(), 0, 150)));
            }
            foreach ($res->json('data.list', []) as $d) {
                $m = $d['metrics'] ?? [];
                $rows[] = ['day' => substr((string) ($d['dimensions']['stat_time_day'] ?? ''), 0, 10), 'campaign_id' => (string) ($d['dimensions']['campaign_id'] ?? ''), 'name' => (string) ($m['campaign_name'] ?? ''),
                    'spend' => (float) ($m['spend'] ?? 0), 'currency' => $m['currency'] ?? null, 'impressions' => (int) ($m['impressions'] ?? 0), 'clicks' => (int) ($m['clicks'] ?? 0)];
            }
            if ($page >= (int) $res->json('data.page_info.total_page', 1)) {
                break;
            }
        }

        return array_values(array_filter($rows, fn ($r) => $r['day'] !== '' && $r['campaign_id'] !== ''));
    }
}
