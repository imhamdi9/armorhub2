<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommissionDistribution;

class CommissionDashboardController extends Controller
{
    public function commissions()
    {
        return response()->json([

            'total_commissions' =>
                CommissionDistribution::count(),

            'total_amount' =>
                CommissionDistribution::sum('amount'),

            'pending_amount' =>
                CommissionDistribution::where('status', 'pending')
                    ->sum('amount'),

            'paid_amount' =>
                CommissionDistribution::where('status', 'paid')
                    ->sum('amount'),

            'pending_count' =>
                CommissionDistribution::where('status', 'pending')
                    ->count(),

            'paid_count' =>
                CommissionDistribution::where('status', 'paid')
                    ->count(),

        ]);
    }

    public function earnings()
    {
        return response()->json([

            'platform' =>
                CommissionDistribution::where('type', 'platform')
                    ->sum('amount'),

            'companies' =>
                CommissionDistribution::where('type', 'company')
                    ->sum('amount'),

            'partners' =>
                CommissionDistribution::where('type', 'partner')
                    ->sum('amount'),

        ]);
    }
}