<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrderRefundRequest;
use App\Models\User;
use App\Services\OrderRefundRequests;
use App\Support\Audit;
use Illuminate\Http\Request;

/** The queue of return requests that need a person to decide. */
class RefundRequestAdminController extends Controller
{
    public function index(Request $request)
    {
        return OrderRefundRequest::with('order.product:id,name')->where('status', $request->query('status', 'requested'))->latest('id')->limit(100)->get()->map(fn ($r) => [
            'id' => $r->id, 'order_id' => $r->order_id, 'product' => $r->order?->product?->name, 'amount' => (float) ($r->order?->payable_amount ?? 0), 'currency' => $r->order?->currency,
            'kind' => $r->kind, 'reason' => $r->reason, 'status' => $r->status, 'customer' => User::whereKey($r->customer_id)->value('name'), 'created_at' => $r->created_at?->toIso8601String(),
        ])->values();
    }

    public function approve(Request $request, int $id, OrderRefundRequests $requests)
    {
        $r = $requests->approve(OrderRefundRequest::findOrFail($id), $request->user(), $request->input('note'));
        Audit::record('orders.refund_request_approved', $r, ['order' => $r->order_id], $request->user());

        return ['id' => $r->id, 'status' => $r->status];
    }

    public function reject(Request $request, int $id, OrderRefundRequests $requests)
    {
        $data = $request->validate(['note' => 'required|string|min:3|max:300']);
        $r = $requests->reject(OrderRefundRequest::findOrFail($id), $request->user(), $data['note']);
        Audit::record('orders.refund_request_rejected', $r, ['order' => $r->order_id], $request->user());

        return ['id' => $r->id, 'status' => $r->status];
    }
}
