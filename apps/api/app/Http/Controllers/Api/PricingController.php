<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Pricing\CalculateProductPriceService;


class PricingController extends Controller
{

    public function calculate(
        Request $request,
        CalculateProductPriceService $service
    )
    {

        $validated = $request->validate([
            'product_id'=>'required|integer',
            'targets'=>'required|array',
            'targets.*'=>'integer',
            'target_type'=>'nullable|string',
        ]);


        return response()->json(
            $service->calculate(
                $validated['product_id'],
                $validated['targets'],
                $validated['target_type'] ?? null
            )
        );

    }

}
