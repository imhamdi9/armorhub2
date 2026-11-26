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
    /** A market's agent reaches only the returns of that market's orders; the platform team reaches all. */
    private function scoped(Request $request)
    {
        $ids = \App\Support\MarketScope::companyIds($request->user());

        return OrderRefundRequest::query()->when($ids !== null, fn ($q) => $q->whereIn('order_id', \App\Models\Order::withoutGlobalScopes()->whereIn('company_id', $ids)->select('id')));
    }

    public function index(Request $request)
    {
        return $this->scoped($request)->with('order.product:id,name,name_ar')->whereIn('status', $request->query('status') ? [$request->query('status')] : ['requested', 'awaiting_pickup', 'collected'])->latest('id')->limit(100)->get()->map(fn ($r) => [
            'id' => $r->id, 'order_id' => $r->order_id, 'product' => $r->order?->product?->name, 'amount' => (float) ($r->order?->payable_amount ?? 0), 'currency' => $r->order?->currency,
            'kind' => $r->kind, 'reason' => $r->reason, 'photos' => $r->photos ?? [], 'status' => $r->status, 'pickup_day' => $r->pickup_day?->toDateString(), 'pickup_note' => $r->pickup_note, 'customer' => User::whereKey($r->customer_id)->value('name'), 'created_at' => $r->created_at?->toIso8601String(),
        ])->values();
    }

    public function approve(Request $request, int $id, OrderRefundRequests $requests)
    {
        $r = $requests->approve($this->scoped($request)->findOrFail($id), $request->user(), $request->input('note'));
        Audit::record('orders.refund_request_approved', $r, ['order' => $r->order_id], $request->user());

        return ['id' => $r->id, 'status' => $r->status];
    }

    public function reject(Request $request, int $id, OrderRefundRequests $requests)
    {
        $data = $request->validate(['note' => 'required|string|min:3|max:300']);
        $r = $requests->reject($this->scoped($request)->findOrFail($id), $request->user(), $data['note']);
        Audit::record('orders.refund_request_rejected', $r, ['order' => $r->order_id], $request->user());

        return ['id' => $r->id, 'status' => $r->status];
    }

    /** The collector types the code the customer gave them. */
    public function collect(Request $request, int $id, OrderRefundRequests $requests)
    {
        $data = $request->validate(['code' => 'required|string|max:8']);
        $r = $requests->collect($this->scoped($request)->findOrFail($id), $request->user(), $data['code']);
        Audit::record('orders.return_collected', $r, ['order' => $r->order_id], $request->user());

        return ['id' => $r->id, 'status' => $r->status];
    }

    /** The returned parcel is checked: passed refunds the customer, failed does not. */
    public function inspect(Request $request, int $id, OrderRefundRequests $requests)
    {
        $data = $request->validate(['passed' => 'required|boolean', 'note' => 'nullable|string|max:300']);
        $r = $requests->inspect($this->scoped($request)->findOrFail($id), $request->user(), (bool) $data['passed'], $data['note'] ?? null);
        Audit::record('orders.return_inspected', $r, ['order' => $r->order_id, 'passed' => (bool) $data['passed']], $request->user());

        return ['id' => $r->id, 'status' => $r->status, 'inspection' => $r->inspection];
    }
}
