<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommissionDistribution;
use Illuminate\Http\Request;

class CommissionDistributionController extends Controller
{
    public function index(Request $request)
{
    $query = CommissionDistribution::with([
        'commission',
        'company',
        'partner'
    ]);

    if ($request->filled('status')) {
        $query->where('status', $request->status);
    }

    if ($request->filled('company_id')) {
        $query->where('company_id', $request->company_id);
    }

    if ($request->filled('partner_id')) {
        $query->where(
            'partner_id',
            $request->partner_id
        );
    }

    if ($request->filled('type')) {
        $query->where('type', $request->type);
    }

    if ($request->filled('from')) {
        $query->whereDate(
            'created_at',
            '>=',
            $request->from
        );
    }

    if ($request->filled('to')) {
        $query->whereDate(
            'created_at',
            '<=',
            $request->to
        );
    }

    return response()->json(
        $query->latest()->paginate(20)
    );
}

    public function pay(CommissionDistribution $commissionDistribution)
    {
        if ($commissionDistribution->status === 'paid') {
            return response()->json([
                'message' => 'Already paid'
            ], 400);
        }

        $commissionDistribution->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        return response()->json([
            'message' => 'Commission paid successfully',
            'distribution' => $commissionDistribution,
        ]);
    }
}