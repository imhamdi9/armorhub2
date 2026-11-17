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

        return $kpis->compute($from, $to, isset($data['market']) ? strtoupper($data['market']) : null);
    }
}
