<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Pricing\CalculateFilmPriceService;


class PricingController extends Controller
{

    public function calculate(
        Request $request,
        CalculateFilmPriceService $service
    )
    {

        $validated = $request->validate([
            'film_id'=>'required|integer',
            'parts'=>'required|array',
            'parts.*'=>'integer',
        ]);


        return response()->json(
            $service->calculate(
                $validated['film_id'],
                $validated['parts']
            )
        );

    }

}