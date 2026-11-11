<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SellerLedgerEntry;
use App\Models\SellerPayout;
use App\Services\SellerPayoutService;
use Illuminate\Http\Request;

/** A seller's own money: what is held until delivery, what can be paid, what has been paid, and the statement behind it. */
class SellerEarningsController extends Controller
{
    public function show(Request $request, SellerPayoutService $service)
    {
        $user = $request->user();
        abort_unless($user->hasRole('seller') && $user->company_id, 403);

        return [
            'balances' => (object) $service->balances($user->company_id),
            'entries' => SellerLedgerEntry::where('company_id', $user->company_id)->latest('id')->limit(100)->get(['id', 'order_id', 'type', 'amount', 'currency', 'status', 'available_at', 'payout_id', 'created_at']),
            'payouts' => SellerPayout::where('company_id', $user->company_id)->latest('id')->limit(50)->get(['id', 'amount', 'currency', 'status', 'reference', 'paid_at', 'created_at']),
        ];
    }
}
