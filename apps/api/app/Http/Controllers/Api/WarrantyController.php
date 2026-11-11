<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Warranty;
use Illuminate\Http\Request;

class WarrantyController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return Warranty::with(['product:id,name', 'order:id,total_amount,currency'])
            ->when($user->hasRole('customer'), fn ($q) => $q->where('customer_id', $user->id))
            ->orderByDesc('ends_at')
            ->get()
            ->map(fn (Warranty $w) => $w->toArray() + ['active' => $w->ends_at->isFuture()]);
    }
}
