<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payment\PaymentFinalizer;
use App\Services\Payment\PaymentGateway;
use App\Services\Payment\PaymentUnavailable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    use AuthorizesRequests;

    public function store(
        Request $request,
        Order $order,
        PaymentFinalizer $finalizer,
        PaymentGateway $gateway
    )
    {
        $this->authorize('view', $order);

        $data = $request->validate([
            'amount' => 'required|numeric',
            'payment_method' => 'nullable|string',
        ]);

        if ($order->status === OrderStatus::Paid) {
            return response()->json(['message' => 'Order already paid'], 422);
        }

        if (! in_array($order->status, [OrderStatus::PartnerSelected, OrderStatus::AwaitingPayment], true)) {
            throw ValidationException::withMessages([
                'order' => ['Select a partner quote before paying.'],
            ]);
        }

        if (round((float) $data['amount'], 2) !== round((float) $order->total_amount, 2)) {
            throw ValidationException::withMessages([
                'amount' => ['The amount must match the order total.'],
            ]);
        }

        try {
            $charge = $gateway->charge($order, (float) $order->total_amount, $data['payment_method'] ?? null);
        } catch (PaymentUnavailable $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        if ($charge['status'] === 'failed') {
            return response()->json(['message' => $charge['message'] ?? 'The payment was declined.'], 402);
        }

        // Hosted checkout: remember the attempt; the webhook completes it.
        if ($charge['status'] === 'redirect') {
            Payment::create([
                'company_id' => $order->company_id,
                'order_id' => $order->id,
                'amount' => $order->total_amount,
                'currency' => $order->currency,
                'payment_method' => $data['payment_method'] ?? null,
                'status' => 'pending',
                'transaction_id' => $charge['transaction_id'],
            ]);

            return response()->json(['message' => 'Continue to the payment page.', 'checkout_url' => $charge['url']], 202);
        }

        $result = $finalizer->complete($order, $charge, $data['payment_method'] ?? null, $request->user());

        if ($result === null) {
            return response()->json(['message' => 'Order already paid'], 422);
        }

        return response()->json([
            'message' => 'Payment completed',
            'payment' => $result[0],
            'commission' => $result[1]->load('distributions'),
            'order' => $order->fresh(),
        ], 201);
    }
}
