<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Commission;

class CommissionController extends Controller
{
    public function index()
    {
        $commissions = Commission::with([
            'order',
            'distributions.company',
            'distributions.partner',
        ])->latest()->get();

        return response()->json($commissions);
    }

    public function show($id)
    {
        $commission = Commission::with([
            'order',
            'distributions.company',
            'distributions.partner',
        ])->findOrFail($id);

        return response()->json($commission);
    }
}