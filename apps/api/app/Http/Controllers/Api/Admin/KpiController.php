<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Kpis;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/** The numbers that say whether the business works. Platform administrators only. */
class KpiController extends Controller
{
    public function show(Request $request, Kpis $kpis)
    {
        $data = $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from', 'market' => 'nullable|string|size:2']);
        $to = CarbonImmutable::parse($data['to'] ?? now());
        $from = CarbonImmutable::parse($data['from'] ?? $to->subDays(29));

        $market = isset($data['market']) ? strtoupper($data['market']) : null;
        $out = $kpis->compute($from, $to, $market);

        // The same number of days just before, so every figure can be read as "up or down from last time", and a day-by-day line.
        $days = (int) $from->startOfDay()->diffInDays($to->startOfDay()) + 1;
        $prevTo = $from->subDay();
        $prevFrom = $prevTo->subDays($days - 1);
        $out['previous'] = ['period' => ['from' => $prevFrom->toDateString(), 'to' => $prevTo->toDateString()]] + array_intersect_key($kpis->compute($prevFrom, $prevTo, $market), array_flip(['gmv', 'net_revenue', 'average_order_value', 'orders', 'rfq']));
        $out['series'] = $kpis->dailyGmv($from, $to, $market);

        return $out;
    }
}
