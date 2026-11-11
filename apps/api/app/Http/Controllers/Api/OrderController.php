<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Models\Order;
use App\Modules\IndustryRegistry;
use App\Models\PartnerQuote;
use App\Models\Partner;
use App\Services\Pricing\CalculateProductPriceService;
use Illuminate\Http\Request;
use App\Services\Location\FindNearbyPartnersService;

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
        CalculateProductPriceService $pricing,
        FindNearbyPartnersService $location,
        IndustryRegistry $registry
    ) {
        $this->authorize('create', Order::class);

        $data = $request->validate([
            'product_id' => 'required|integer',
            'subject_type' => ['nullable', 'string', 'in:'.implode(',', array_keys($registry->subjectModels()))],
            'subject_id' => ['nullable', 'integer', 'required_with:subject_type'],
            'target_type' => 'nullable|string',
            'targets' => 'required|array',
            'targets.*' => 'integer',
            'customer_id' => 'nullable|integer',
        ]);

        $price = $pricing->calculate(
            $data['product_id'],
            $data['targets'],
            $data['target_type'] ?? null
        );

        $order = Order::create([
            'company_id' => auth()->user()->company_id,
            'customer_id' => auth()->id(),
            'product_id' => $data['product_id'],
            'subject_type' => $data['subject_type'] ?? null,
            'subject_id' => $data['subject_id'] ?? null,
            'status' => 'pending',
            'product_amount' => $price['total'],
            'service_amount' => 0,
            'commission_amount' => 0,
            'total_amount' => $price['total'],
            'currency' => $price['currency'],
        ]);

        $user = auth()->user();

        if (!$user->location_enabled || !$user->latitude || !$user->longitude) {
            return response()->json([
             'message' => 'Please enable location to find nearby partners'
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
          'message' => 'No partners available'
          ], 422);
        }

        foreach ($centers as $center) {
            PartnerQuote::create([
                'company_id' => auth()->user()->company_id,
                'order_id' => $order->id,
                'partner_id' => $center->id,
                'price' => 0,
                'status' => 'pending',
            ]);
        }

        return response()->json([
            'message' => 'Order created successfully',
            'order' => $order,
            'partner_quotes' => $centers->count(),
        ], 201);
    }
}