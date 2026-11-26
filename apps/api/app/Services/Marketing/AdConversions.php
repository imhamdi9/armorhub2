<?php

namespace App\Services\Marketing;

use App\Models\AdConversionLog;
use App\Models\Company;
use App\Models\Order;
use App\Models\PartnerApplication;
use App\Support\Settings\Settings;
use Illuminate\Support\Facades\Http;

/**
 * Tells Meta, TikTok and Google Analytics about a purchase or a lead from the server, so a blocked browser pixel does not lose it.
 * Strict by design: nothing leaves unless the visitor allowed marketing (the order carries the campaign context only then),
 * the market has the platform switched on (an id and a token), and personal details are sent as SHA-256 hashes only.
 * The same event id is used by the browser pixel, so the platforms count each purchase once.
 */
class AdConversions
{
    public const PLATFORMS = ['meta', 'tiktok', 'google'];

    /** A platform that did not accept an event is tried again up to this many times in all, then the admins are told. */
    public const MAX_TRIES = 4;

    public function __construct(private Settings $settings)
    {
    }

    public function purchase(Order $order): void
    {
        $tenantId = $this->tenantOfCompany($order->company_id);
        $address = is_array($order->shipping_address) ? $order->shipping_address : [];
        $this->send('purchase', 'order', $order->id, 'order-'.$order->id, $tenantId, $order->ad_context, $order->attribution, [
            'value' => round((float) $order->payable_amount, 2), 'currency' => (string) $order->currency, 'order_id' => (string) $order->id,
            'email' => $order->customer?->contact_email ?: ($order->guest_email ?: $order->customer?->email),
            'phone' => $address['phone'] ?? null, 'name' => $address['name'] ?? null,
            'content_ids' => [(string) $order->product_id],
        ]);
    }

    /** Money given back: Google Analytics has a refund event (Meta and TikTok have none, so only they keep the original sale). */
    public function refund(Order $order, float $amount, int $orderEventId): void
    {
        $this->send('refund', 'order', $order->id, 'refund-'.$order->id.'-'.$orderEventId, $this->tenantOfCompany($order->company_id), $order->ad_context, $order->attribution, [
            'value' => round($amount, 2), 'currency' => (string) $order->currency, 'order_id' => (string) $order->id, 'content_ids' => [],
        ]);
    }

    public function approved(PartnerApplication $application): void
    {
        $this->send('approved', 'application', $application->id, 'approved-'.$application->id, $application->tenant_id ?? null, $application->ad_context, $application->attribution, [
            'email' => $application->email ?? null, 'phone' => $application->phone ?? null, 'name' => $application->contact_name ?? null,
            'value' => 0, 'currency' => '', 'order_id' => null, 'content_ids' => [],
        ]);
    }

    public function lead(PartnerApplication $application): void
    {
        $this->send('lead', 'application', $application->id, 'lead-'.$application->id, $application->tenant_id ?? null, $application->ad_context, $application->attribution, [
            'email' => $application->email ?? null, 'phone' => $application->phone ?? null, 'name' => $application->contact_name ?? null,
            'value' => 0, 'currency' => '', 'order_id' => null, 'content_ids' => [],
        ]);
    }

    /** A made-up purchase, to prove the connection. @return list<array{platform: string, status: string, http_status: int|null, note: string|null}> */
    public function test(?int $tenantId): array
    {
        $id = 'test-'.now()->format('His').random_int(10, 99);
        $this->send('purchase', 'test', 0, $id, $tenantId, ['ip' => '203.0.113.7', 'ua' => 'Connection test', 'source_url' => rtrim((string) config('platform.web_url'), '/').'/'], ['utm_source' => 'connection-test'],
            ['value' => 1.0, 'currency' => 'USD', 'order_id' => $id, 'email' => 'test@example.com', 'phone' => '15550100', 'name' => 'Test', 'content_ids' => ['test']]);

        return AdConversionLog::where('event_id', $id)->get(['platform', 'status', 'http_status', 'note'])->map->toArray()->all();
    }

    /** @param array<string, mixed> $data */
    private function send(string $event, string $subjectType, int $subjectId, string $eventId, ?int $tenantId, ?array $context, ?array $attribution, array $data): void
    {
        if (! $context) {
            $this->log('all', $event, $eventId, $subjectType, $subjectId, $tenantId, 'skipped', null, 'The visitor did not allow marketing cookies.');

            return;
        }

        $this->settings->inMarket($tenantId, function () use ($event, $subjectType, $subjectId, $eventId, $tenantId, $context, $attribution, $data) {
            $s = $this->settings;
            $jobs = [
                'meta' => $event !== 'refund' && $s->get('marketing.meta_pixel_id') && $s->get('marketing.meta_access_token') ? fn () => $this->meta($event, $eventId, $context, $data) : null,
                'tiktok' => $event !== 'refund' && $s->get('marketing.tiktok_pixel_id') && $s->get('marketing.tiktok_access_token') ? fn () => $this->tiktok($event, $eventId, $context, $attribution ?? [], $data) : null,
                'google' => $s->get('marketing.ga4_id') && $s->get('marketing.ga4_api_secret') ? fn () => $this->google($event, $eventId, $context, $attribution ?? [], $data) : null,
            ];
            foreach ($jobs as $platform => $job) {
                if (! $job) {
                    continue;
                }
                if (AdConversionLog::where(['platform' => $platform, 'event_id' => $eventId, 'status' => 'sent'])->exists()) {
                    continue;          // sent before (a replayed event): never twice
                }
                if (AdConversionLog::where(['platform' => $platform, 'event_id' => $eventId, 'status' => 'failed'])->count() >= self::MAX_TRIES) {
                    continue;          // given up: the admins were told
                }
                try {
                    $response = $job();
                    $ok = $response->successful();
                    $this->log($platform, $event, $eventId, $subjectType, $subjectId, $tenantId, $ok ? 'sent' : 'failed', $response->status(), $ok ? null : mb_substr($response->body(), 0, 280));
                    $note = $ok ? null : mb_substr($response->body(), 0, 120);
                } catch (\Throwable $e) {
                    $this->log($platform, $event, $eventId, $subjectType, $subjectId, $tenantId, 'failed', null, mb_substr($e->getMessage(), 0, 280));
                    $ok = false;
                    $note = mb_substr($e->getMessage(), 0, 120);
                }
                if (! $ok && $subjectType !== 'test' && AdConversionLog::where(['platform' => $platform, 'event_id' => $eventId, 'status' => 'failed'])->count() >= self::MAX_TRIES) {
                    AdAlerts::send('conversions:'.$platform, 'An ad platform is not receiving your sales', ':platform: :note', ['platform' => ucfirst($platform), 'note' => (string) $note]);
                }
            }
        });
    }

    private function hash(?string $v, bool $digits = false): ?string
    {
        $v = trim(mb_strtolower((string) $v));
        if ($digits) {
            $v = preg_replace('/\D+/', '', $v) ?? '';
        }
        if ($v === '' || str_ends_with($v, '@guest.invalid')) {
            return null;
        }

        return hash('sha256', $v);
    }

    private function meta(string $event, string $eventId, array $ctx, array $d)
    {
        $user = array_filter([
            'em' => ($h = $this->hash($d['email'] ?? null)) ? [$h] : null, 'ph' => ($h = $this->hash($d['phone'] ?? null, true)) ? [$h] : null,
            'client_ip_address' => $ctx['ip'] ?? null, 'client_user_agent' => $ctx['ua'] ?? null, 'fbp' => $ctx['fbp'] ?? null, 'fbc' => $ctx['fbc'] ?? null,
        ]);
        $body = ['data' => [array_filter([
            'event_name' => ['purchase' => 'Purchase', 'approved' => 'CompleteRegistration'][$event] ?? 'Lead', 'event_time' => time(), 'event_id' => $eventId, 'action_source' => 'website',
            'event_source_url' => $ctx['source_url'] ?? null, 'user_data' => $user,
            'custom_data' => $event === 'purchase' ? ['currency' => $d['currency'], 'value' => $d['value'], 'order_id' => $d['order_id'], 'content_ids' => $d['content_ids'], 'content_type' => 'product'] : null,
        ])]];
        if ($code = $this->settings->get('marketing.meta_test_code')) {
            $body['test_event_code'] = $code;
        }
        $url = rtrim(config('marketing.endpoints.meta'), '/').'/'.$this->settings->get('marketing.meta_pixel_id').'/events';

        return Http::timeout(config('marketing.timeout'))->asJson()->post($url.'?access_token='.urlencode((string) $this->settings->get('marketing.meta_access_token')), $body);
    }

    private function tiktok(string $event, string $eventId, array $ctx, array $attr, array $d)
    {
        $user = array_filter([
            'email' => $this->hash($d['email'] ?? null), 'phone' => $this->hash($d['phone'] ?? null, true), 'ip' => $ctx['ip'] ?? null, 'user_agent' => $ctx['ua'] ?? null,
            'ttclid' => $attr['ttclid'] ?? null, 'ttp' => $ctx['ttp'] ?? null,
        ]);
        $row = array_filter([
            'event' => ['purchase' => 'CompletePayment', 'approved' => 'CompleteRegistration'][$event] ?? 'SubmitForm', 'event_time' => time(), 'event_id' => $eventId, 'user' => $user,
            'page' => array_filter(['url' => $ctx['source_url'] ?? null]),
            'properties' => $event === 'purchase' ? ['currency' => $d['currency'], 'value' => $d['value'], 'content_type' => 'product', 'order_id' => $d['order_id']] : null,
        ]);
        $body = ['event_source' => 'web', 'event_source_id' => $this->settings->get('marketing.tiktok_pixel_id'), 'data' => [$row]];
        if ($code = $this->settings->get('marketing.tiktok_test_code')) {
            $body['test_event_code'] = $code;
        }

        return Http::timeout(config('marketing.timeout'))->withHeaders(['Access-Token' => (string) $this->settings->get('marketing.tiktok_access_token')])->asJson()->post(config('marketing.endpoints.tiktok'), $body);
    }

    private function google(string $event, string $eventId, array $ctx, array $attr, array $d)
    {
        $money = in_array($event, ['purchase', 'refund'], true);
        $params = array_filter(['transaction_id' => $d['order_id'], 'value' => $money ? $d['value'] : null, 'currency' => $money ? $d['currency'] : null,
            'gclid' => $attr['gclid'] ?? null, 'engagement_time_msec' => 100]);
        $body = ['client_id' => $ctx['ga_client_id'] ?? ('server.'.crc32($eventId)), 'events' => [['name' => ['purchase' => 'purchase', 'refund' => 'refund', 'approved' => 'qualify_lead'][$event] ?? 'generate_lead', 'params' => $params]]];
        $query = http_build_query(['measurement_id' => $this->settings->get('marketing.ga4_id'), 'api_secret' => $this->settings->get('marketing.ga4_api_secret')]);

        return Http::timeout(config('marketing.timeout'))->asJson()->post(config('marketing.endpoints.google').'?'.$query, $body);
    }

    private function tenantOfCompany(?int $companyId): ?int
    {
        return $companyId ? ((int) Company::withoutGlobalScopes()->whereKey($companyId)->value('tenant_id') ?: null) : null;
    }

    private function log(string $platform, string $event, string $eventId, string $type, int $id, ?int $tenantId, string $status, ?int $http, ?string $note): void
    {
        AdConversionLog::create(['platform' => $platform, 'event_name' => $event, 'event_id' => $eventId, 'subject_type' => $type, 'subject_id' => $id, 'tenant_id' => $tenantId, 'status' => $status, 'http_status' => $http, 'note' => $note]);
    }
}
