<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InstallationQuote;
use Illuminate\Http\Request;
use App\Services\QuoteService;

class InstallationQuoteController extends Controller
{

    public function index($order)
    {
        return InstallationQuote::where('order_id', $order)
            ->with('installationCenter')
            ->get();
    }

    public function accept(
    InstallationQuote $installationQuote,
    QuoteService $quoteService
    )
    {
        $order = $quoteService->accept($installationQuote);

        return response()->json([
          'message' => 'Installation quote accepted',
          'order' => $order,
        ]);
}


    public function update(
        Request $request,
        InstallationQuote $installationQuote
    ) {

        $data = $request->validate([
            'price' => 'required|numeric|min:0',
            'status' => 'nullable|string',
        ]);


        $installationQuote->update([
            'price' => $data['price'],
            'status' => $data['status'] ?? 'submitted',
        ]);


        return response()->json([
            'message' => 'Installation quote submitted',
            'quote' => $installationQuote
        ]);
    }

}