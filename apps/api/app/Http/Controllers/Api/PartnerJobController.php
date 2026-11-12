<?php

namespace App\Http\Controllers\Api;

use App\Enums\QuoteStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PartnerQuote;
use Illuminate\Http\Request;

class PartnerJobController extends Controller
{
    /** Paid jobs assigned to the signed-in user's partner, newest activity first. */
    public function index(Request $request)
    {
        $partnerId = $request->user()->partner_id;

        if (! $partnerId) {
            return response()->json(['message' => 'Your account is not linked to a partner yet. Ask your company owner to assign you to one.'], 422);
        }

        $orderIds = PartnerQuote::withoutGlobalScopes()
            ->where('partner_id', $partnerId)
            ->where('status', QuoteStatus::Accepted)
            ->pluck('order_id');

        return Order::withoutGlobalScopes()
            ->with(['product:id,name,warranty_years', 'subject', 'customer:id,name,city,country', 'events.user:id,name'])
            ->whereIn('id', $orderIds)
            ->whereIn('status', ['paid', 'appointment_scheduled', 'service_in_progress', 'completed'])
            ->latest('updated_at')
            ->limit(200)
            ->get();
    }
}
