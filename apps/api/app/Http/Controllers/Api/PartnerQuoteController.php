<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PartnerQuote;
use Illuminate\Http\Request;
use App\Services\QuoteService;

class PartnerQuoteController extends Controller
{

    public function index($order)
    {
        return PartnerQuote::where('order_id', $order)
            ->with('partner')
            ->get();
    }

    public function accept(
    PartnerQuote $partnerQuote,
    QuoteService $quoteService
    )
    {
        $order = $quoteService->accept($partnerQuote);

        return response()->json([
          'message' => 'Partner quote accepted',
          'order' => $order,
        ]);
}


    public function update(
        Request $request,
        PartnerQuote $partnerQuote
    ) {

        $data = $request->validate([
            'price' => 'required|numeric|min:0',
            'status' => 'nullable|string',
        ]);


        $partnerQuote->update([
            'price' => $data['price'],
            'status' => $data['status'] ?? 'submitted',
        ]);


        return response()->json([
            'message' => 'Partner quote submitted',
            'quote' => $partnerQuote
        ]);
    }

}