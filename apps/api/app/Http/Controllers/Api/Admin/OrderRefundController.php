<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderRefunds;
use App\Support\Audit;
use Illuminate\Http\Request;

/** Platform administrators give customers their money back for product orders. */
class OrderRefundController extends Controller
{
    public function refund(Request $request, int $id, OrderRefunds $refunds)
    {
        $data = $request->validate(['reason' => 'required|string|min:3|max:300']);
        $order = $refunds->refund(Order::withoutGlobalScopes()->findOrFail($id), $request->user(), $data['reason']);
        Audit::record('orders.refunded', $order, ['amount' => (string) $order->refund_amount], $request->user());

        return $this->row($order);
    }

    public function sent(Request $request, int $id, OrderRefunds $refunds)
    {
        $data = $request->validate(['reference' => 'required|string|max:120']);
        $order = $refunds->markSent(Order::withoutGlobalScopes()->findOrFail($id), $request->user(), $data['reference']);
        Audit::record('orders.refund_sent', $order, ['reference' => $data['reference']], $request->user());

        return $this->row($order);
    }

    private function row(Order $o): array
    {
        return ['id' => $o->id, 'refund_status' => $o->refund_status, 'refund_amount' => (float) $o->refund_amount, 'currency' => $o->currency, 'reference' => $o->refund_reference];
    }
}
