<?php

namespace App\Services\Marketing;

use Illuminate\Http\Request;

/**
 * Reads the campaign a visitor came from. The browser sends the X-Campaign header only after the visitor allowed marketing cookies,
 * so its presence is the consent: without it nothing is stored and nothing is ever sent to an ad platform.
 */
class CampaignCapture
{
    /** @return array{attribution: array<string, string>, ad_context: array<string, string>}|null */
    public static function fromRequest(?Request $request = null): ?array
    {
        $request ??= request();
        $raw = $request?->header('X-Campaign');
        if (! is_string($raw) || $raw === '' || strlen($raw) > 4000) {
            return null;
        }
        $data = json_decode($raw, true);
        if (! is_array($data)) {
            return null;
        }

        $clean = fn (array $keys) => collect($keys)->mapWithKeys(fn ($k) => isset($data[$k]) && is_scalar($data[$k]) && trim((string) $data[$k]) !== ''
            ? [$k => mb_substr(strip_tags(trim((string) $data[$k])), 0, 250)] : [])->all();

        return [
            'attribution' => $clean(config('marketing.attribution_keys')),
            'ad_context' => $clean(config('marketing.context_keys')) + ['ip' => (string) $request->ip(), 'ua' => mb_substr((string) $request->userAgent(), 0, 250)],
        ];
    }

    /** Puts the campaign on a model that is about to be created. */
    public static function stamp(\Illuminate\Database\Eloquent\Model $model): void
    {
        if ($found = self::fromRequest()) {
            $model->attribution = $found['attribution'];
            $model->ad_context = $found['ad_context'];
        }
    }
}
