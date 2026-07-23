<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Models\Order;
use App\Models\InstallationQuote;
use App\Models\InstallationCenter;
use App\Services\Pricing\CalculateFilmPriceService;
use Illuminate\Http\Request;
use App\Services\Location\FindNearbyInstallationCentersService;

class OrderController extends Controller
{
    use AuthorizesRequests;
    public function show(Order $order)
    {
     $this->authorize('view', $order);

     return response()->json($order);
    }
    
    public function index()
    {
      $this->authorize('viewAny', Order::class);

       $user = auth()->user();
       
       
      if ($user->hasRole('platform-admin')) {
        return Order::all();
     }

     if ($user->hasRole('customer')) {
        return Order::where(
            'customer_id',
            $user->id
        )->get();
     }

     return Order::where(
        'company_id',
        $user->company_id
     )->get();
    }

    public function store(
        Request $request,
        CalculateFilmPriceService $pricing,
        FindNearbyInstallationCentersService $location
    ) {
        $this->authorize('create', Order::class);

        $data = $request->validate([
            'film_id' => 'required|integer',
            'vehicle_model_id' => 'required|integer',
            'parts' => 'required|array',
            'customer_id' => 'nullable|integer',
        ]);

        $price = $pricing->calculate(
            $data['film_id'],
            $data['parts']
        );

        $order = Order::create([
            'company_id' => auth()->user()->company_id,
            'customer_id' => auth()->id(),
            'film_id' => $data['film_id'],
            'vehicle_model_id' => $data['vehicle_model_id'],
            'status' => 'pending',
            'film_amount' => $price['total'],
            'installation_amount' => 0,
            'commission_amount' => 0,
            'total_amount' => $price['total'],
            'currency' => $price['currency'],
        ]);

        $user = auth()->user();

        if (!$user->location_enabled || !$user->latitude || !$user->longitude) {
            return response()->json([
             'message' => 'Please enable location to find nearby installation centers'
            ], 422);
        }

        $centers = $location->executeWithFallback(
          $user->latitude,
          $user->longitude,
          $user->city,
          $user->country
        );

        if ($centers->isEmpty()) {
          return response()->json([
          'message' => 'No installation centers available'
          ], 422);
        }

        foreach ($centers as $center) {
            InstallationQuote::create([
                'company_id' => auth()->user()->company_id,
                'order_id' => $order->id,
                'installation_center_id' => $center->id,
                'price' => 0,
                'status' => 'pending',
            ]);
        }

        return response()->json([
            'message' => 'Order created successfully',
            'order' => $order,
            'installation_quotes' => $centers->count(),
        ], 201);
    }
}