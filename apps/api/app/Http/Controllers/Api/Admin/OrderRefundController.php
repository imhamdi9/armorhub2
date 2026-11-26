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

    /** Sends the customer's money back through the provider that took it, when that provider can do it, and records the reference. */
    public function sendThrough(Request $request, int $id, OrderRefunds $refunds, \App\Services\Payment\PaymentProviders $providers)
    {
        $order = Order::withoutGlobalScopes()->findOrFail($id);
        abort_unless($order->refund_status === 'pending', 422, 'There is no pending refund for this order.');

        $payment = \App\Models\Payment::withoutGlobalScopes()->where('order_id', $order->id)->where('status', 'completed')->latest('id')->first();
        $provider = $payment && $payment->provider && $providers->has($payment->provider) ? $providers->get($payment->provider) : null;
        if (! $provider || ! method_exists($provider, 'refund')) {
            return response()->json(['message' => 'This payment provider cannot send refunds from here. Send it from the provider\'s dashboard and record the reference.'], 422);
        }
        $result = app(\App\Support\Settings\Settings::class)->inMarket(
            \App\Models\Company::withoutGlobalScopes()->whereKey($order->company_id)->value('tenant_id'),
            fn () => $provider->refund((string) $payment->transaction_id, (float) $order->refund_amount, (string) $order->currency, $providers->config($provider->key())),
        );
        if (! ($result['ok'] ?? false)) {
            return response()->json(['message' => $result['message'] ?? 'The provider did not accept the refund.'], 422);
        }
        $order = $refunds->markSent($order, $request->user(), (string) $result['reference']);
        Audit::record('orders.refund_sent_through_provider', $order, ['reference' => $result['reference']], $request->user());

        return $this->row($order);
    }

    /** Part of the money goes back while the order stands. */
    public function partial(Request $request, int $id, \App\Services\PartialRefunds $partial)
    {
        $data = $request->validate(['amount' => 'required|numeric|min:0.01|max:10000000', 'reason' => 'required|string|min:3|max:300']);
        $order = $partial->refund(Order::withoutGlobalScopes()->findOrFail($id), $request->user(), (float) $data['amount'], trim(strip_tags($data['reason'])));
        Audit::record('orders.partially_refunded', $order, ['amount' => (string) $order->partial_refund_amount], $request->user());

        return $this->partialRow($order);
    }

    public function partialSent(Request $request, int $id, \App\Services\PartialRefunds $partial)
    {
        $data = $request->validate(['reference' => 'required|string|max:120']);
        $order = $partial->markSent(Order::withoutGlobalScopes()->findOrFail($id), $request->user(), $data['reference']);
        Audit::record('orders.partial_refund_sent', $order, ['reference' => $data['reference']], $request->user());

        return $this->partialRow($order);
    }

    /** Sends the partial refund back through the provider that took the payment, when it can, and records the reference. */
    public function partialSendThrough(Request $request, int $id, \App\Services\PartialRefunds $partial, \App\Services\Payment\PaymentProviders $providers)
    {
        $order = Order::withoutGlobalScopes()->findOrFail($id);
        abort_unless($order->partial_refund_status === 'pending', 422, 'There is no pending partial refund for this order.');

        $payment = \App\Models\Payment::withoutGlobalScopes()->where('order_id', $order->id)->where('status', 'completed')->latest('id')->first();
        $provider = $payment && $payment->provider && $providers->has($payment->provider) ? $providers->get($payment->provider) : null;
        if (! $provider || ! method_exists($provider, 'refund')) {
            return response()->json(['message' => 'This payment provider cannot send refunds from here. Send it from the provider\'s dashboard and record the reference.'], 422);
        }
        $result = app(\App\Support\Settings\Settings::class)->inMarket(
            \App\Models\Company::withoutGlobalScopes()->whereKey($order->company_id)->value('tenant_id'),
            fn () => $provider->refund((string) $payment->transaction_id, (float) $order->partial_refund_amount, (string) $order->currency, $providers->config($provider->key())),
        );
        if (! ($result['ok'] ?? false)) {
            return response()->json(['message' => $result['message'] ?? 'The provider did not accept the refund.'], 422);
        }
        $order = $partial->markSent($order, $request->user(), (string) $result['reference']);
        Audit::record('orders.partial_refund_sent_through_provider', $order, ['reference' => $result['reference']], $request->user());

        return $this->partialRow($order);
    }

    private function partialRow(Order $o): array
    {
        return ['id' => $o->id, 'partial_refund_status' => $o->partial_refund_status, 'partial_refund_amount' => (float) $o->partial_refund_amount, 'currency' => $o->currency, 'reference' => $o->partial_refund_reference];
    }

    private function row(Order $o): array
    {
        return ['id' => $o->id, 'refund_status' => $o->refund_status, 'refund_amount' => (float) $o->refund_amount, 'currency' => $o->currency, 'reference' => $o->refund_reference];
    }
}
