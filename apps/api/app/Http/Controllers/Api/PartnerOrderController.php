<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PartnerQuote;
use Illuminate\Http\Request;
use App\Http\Requests\SubmitPartnerQuoteRequest;

class PartnerOrderController extends Controller
{
    public function submitQuote(
    SubmitPartnerQuoteRequest $request,
    PartnerQuote $partnerQuote
   ){
    if (
        $partnerQuote->partner_id !==
        $request->user()->partner_id
    ) {
        return response()->json([
            'message' => 'Unauthorized.'
        ], 403);
    }

    $partnerQuote->update([
        'price' => $request->price,
        'status' => 'submitted',
        'submitted_at' => now(),
    ]);

    return response()->json([
        'message' => 'Quote submitted successfully.',
        'quote' => $partnerQuote->fresh(),
    ]);
}
    public function index(Request $request)
    {
        $center = $request->user()
            ->partner;

        if (!$center) {
            return response()->json([
                'message' => 'No partner assigned'
            ], 422);
        }

        $quotes = PartnerQuote::with([
                'order',
                'order.product',
                'order.subject'
            ])
            ->where(
                'partner_id',
                $center->id
            )
            ->where(
                'status',
                'pending'
            )
            ->get();

        return response()->json($quotes);
    }
}