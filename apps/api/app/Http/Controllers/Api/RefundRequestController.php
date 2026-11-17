<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderRefundRequest;
use App\Services\OrderRefundRequests;
use Illuminate\Http\Request;

/** A customer asking to cancel a paid order or return it, and what became of the request. */
class RefundRequestController extends Controller
{
    public function store(Request $request, int $id, OrderRefundRequests $requests)
    {
        $data = $request->validate(['reason' => 'required|string|min:3|max:300']);
        $order = Order::withoutGlobalScopes()->where('customer_id', $request->user()->id)->findOrFail($id);

        return response()->json($requests->request($order, $request->user(), $data['reason']), 201);
    }

    /** The newest request for an order, and whether a new one may be made, so the screen shows the right thing. */
    public function show(Request $request, int $id)
    {
        $order = Order::withoutGlobalScopes()->with('product:id,requires_shipping')->where('customer_id', $request->user()->id)->findOrFail($id);
        $last = OrderRefundRequest::where('order_id', $order->id)->latest('id')->first();

        return [
            'request' => $last ? ['id' => $last->id, 'kind' => $last->kind, 'status' => $last->status, 'reason' => $last->reason, 'note' => $last->decision_note, 'created_at' => $last->created_at?->toIso8601String()] : null,
            'refund_status' => $order->refund_status, 'refund_amount' => $order->refund_amount !== null ? (float) $order->refund_amount : null,
            'can_request' => (bool) $order->product?->requires_shipping && ! $order->refund_status && ($last === null || $last->status !== 'requested')
                && in_array($order->status->value ?? $order->status, ['paid', 'appointment_scheduled', 'service_in_progress', 'completed'], true),
            'return_days' => OrderRefundRequests::RETURN_DAYS,
        ];
    }
}
