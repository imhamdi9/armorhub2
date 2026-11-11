<?php

namespace App\Http\Controllers\Api;

use App\Enums\QuoteStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitPartnerQuoteRequest;
use App\Models\PartnerQuote;
use App\Services\QuoteService;
use Illuminate\Http\Request;

class PartnerOrderController extends Controller
{
    /** Quotes waiting on (or already given by) the signed-in user's partner. */
    public function index(Request $request)
    {
        $partner = $request->user()->partner;

        if (! $partner) {
            return response()->json(['message' => 'No partner assigned'], 422);
        }

        // Quotes belong to the customer's company, so the company scope is
        // bypassed and access is limited to this partner explicitly.
        return PartnerQuote::withoutGlobalScopes()
            ->with([
                'order' => fn ($q) => $q->withoutGlobalScopes(),
                'order.product',
                'order.subject',
            ])
            ->where('partner_id', $partner->id)
            ->whereIn('status', [QuoteStatus::Pending, QuoteStatus::Submitted])
            // Cancelled or already-awarded orders no longer need a quote.
            ->whereHas('order', fn ($q) => $q->withoutGlobalScopes()->whereIn('status', ['pending_quotes', 'quotes_received']))
            ->latest()
            ->get();
    }

    public function submitQuote(
        SubmitPartnerQuoteRequest $request,
        int $id,
        QuoteService $quotes
    ) {
        $quote = PartnerQuote::withoutGlobalScopes()->findOrFail($id);

        abort_unless(
            $request->user()->partner_id !== null
                && $quote->partner_id === $request->user()->partner_id,
            403,
            'Unauthorized.'
        );

        return response()->json([
            'message' => 'Quote submitted successfully.',
            'quote' => $quotes->submit($quote, $request->price, $request->user()),
        ]);
    }
}
