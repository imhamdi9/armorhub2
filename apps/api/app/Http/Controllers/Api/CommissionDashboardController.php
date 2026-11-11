<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommissionDistribution;
use Illuminate\Http\Request;

class CommissionDashboardController extends Controller
{
    private function q(Request $request)
    {
        return CommissionDistribution::visibleTo($request->user());
    }

    public function commissions(Request $request)
    {
        return response()->json([
            'total_commissions' => $this->q($request)->count(),
            'total_amount' => $this->q($request)->sum('amount'),
            'pending_amount' => $this->q($request)->where('status', 'pending')->sum('amount'),
            'paid_amount' => $this->q($request)->where('status', 'paid')->sum('amount'),
            'pending_count' => $this->q($request)->where('status', 'pending')->count(),
            'paid_count' => $this->q($request)->where('status', 'paid')->count(),
        ]);
    }

    public function earnings(Request $request)
    {
        return response()->json([
            'platform' => $this->q($request)->where('type', 'platform')->sum('amount'),
            'companies' => $this->q($request)->where('type', 'company')->sum('amount'),
            'partners' => $this->q($request)->where('type', 'partner')->sum('amount'),
        ]);
    }
}
