<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InstallationQuote;
use Illuminate\Http\Request;
use App\Http\Requests\SubmitInstallationQuoteRequest;

class InstallerOrderController extends Controller
{
    public function submitQuote(
    SubmitInstallationQuoteRequest $request,
    InstallationQuote $installationQuote
   ){
    if (
        $installationQuote->installation_center_id !==
        $request->user()->installation_center_id
    ) {
        return response()->json([
            'message' => 'Unauthorized.'
        ], 403);
    }

    $installationQuote->update([
        'price' => $request->price,
        'status' => 'submitted',
        'submitted_at' => now(),
    ]);

    return response()->json([
        'message' => 'Quote submitted successfully.',
        'quote' => $installationQuote->fresh(),
    ]);
}
    public function index(Request $request)
    {
        $center = $request->user()
            ->installationCenter;

        if (!$center) {
            return response()->json([
                'message' => 'No installation center assigned'
            ], 422);
        }

        $quotes = InstallationQuote::with([
                'order',
                'order.film',
                'order.vehicleModel'
            ])
            ->where(
                'installation_center_id',
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