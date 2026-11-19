<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Support\Settings\Settings;
use Illuminate\Http\Request;

/** The ad ids the browser needs to load its pixels (never the secret tokens), for one market. */
class PublicMarketingController extends Controller
{
    public function show(Request $request, Settings $settings)
    {
        $code = strtoupper((string) $request->query('market', ''));
        $tenant = $code !== '' ? Tenant::where('market', $code)->first() : null;

        $read = fn () => [
            'meta_pixel_id' => (string) $settings->get('marketing.meta_pixel_id') ?: null,
            'tiktok_pixel_id' => (string) $settings->get('marketing.tiktok_pixel_id') ?: null,
            'ga4_id' => (string) $settings->get('marketing.ga4_id') ?: null,
            'google_ads_id' => (string) $settings->get('marketing.google_ads_id') ?: null,
            'google_ads_purchase_label' => (string) $settings->get('marketing.google_ads_purchase_label') ?: null,
            'google_ads_lead_label' => (string) $settings->get('marketing.google_ads_lead_label') ?: null,
        ];

        return response()->json($settings->inMarket($tenant?->id, $read))->header('Cache-Control', 'public, max-age=60');
    }
}
