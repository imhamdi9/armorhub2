<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

/** A seller's view of the orders for its own products (paid ones: those are the ones to ship). */
class SellerOrderController extends Controller
{
    private const SHIPPABLE = ['paid', 'appointment_scheduled', 'service_in_progress', 'completed'];

    public function index(Request $request)
    {
        abort_unless($request->user()->hasRole('seller'), 403);

        return $this->mine($request)->with('product:id,name,brand')->latest('id')->paginate(20)
            ->through(fn (Order $o) => $o->setAttribute('customer_name', $o->customer?->name ?? $o->guest_name));
    }

    public function show(Request $request, int $id)
    {
        abort_unless($request->user()->hasRole('seller'), 403);
        $order = $this->mine($request)->with(['product:id,name,brand,requires_shipping', 'events.user:id,name'])->findOrFail($id);

        // The seller needs the delivery address to ship, and nothing else about the buyer.
        return response()->json($order->makeVisible('shipping_address')->setAttribute('customer_name', $order->customer?->name ?? $order->guest_name));
    }

    private function mine(Request $request)
    {
        return Order::withoutGlobalScopes()->with('customer:id,name')->whereIn('status', self::SHIPPABLE)
            ->whereHas('product', fn ($q) => $q->where('seller_id', $request->user()->company_id));
    }
}
