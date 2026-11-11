<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Enums\QuoteStatus;
use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\CommissionDistribution;
use App\Models\Order;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\PartnerQuote;
use App\Services\Commission\CalculateCommissionService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    use AuthorizesRequests;

    public function store(Request $request, Order $order, CalculateCommissionService $commissions)
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

        $result = DB::transaction(function () use ($order, $data, $commissions) {

            // Re-check under a row lock so two simultaneous requests cannot both pay.
            $locked = Order::withoutGlobalScopes()->lockForUpdate()->findOrFail($order->id);

            if ($locked->status === OrderStatus::Paid) {
                throw ValidationException::withMessages(['order' => ['Order already paid']]);
            }

            $payment = Payment::create([
                'company_id' => $order->company_id,
                'order_id' => $order->id,
                'amount' => $order->total_amount,
                'currency' => $order->currency,
                'payment_method' => $data['payment_method'] ?? null,
                'status' => 'completed',
            ]);

            $calc = $commissions->calculate($order->total_amount);

            $commission = Commission::create([
                'order_id' => $order->id,
                'amount' => $calc['amount'],
                'percentage' => $calc['percentage'],
                'status' => 'pending',
            ]);

            $quote = PartnerQuote::withoutGlobalScopes()
                ->where('order_id', $order->id)
                ->where('status', QuoteStatus::Accepted)
                ->first();

            $partner = $quote ? Partner::withoutGlobalScopes()->find($quote->partner_id) : null;

            if ($partner) {
                $split = config('platform.commission_split');

                CommissionDistribution::create([
                    'commission_id' => $commission->id,
                    'type' => 'platform',
                    'amount' => round($commission->amount * $split['platform'], 2),
                    'status' => 'pending',
                ]);

                if ($partner->company_id) {
                    CommissionDistribution::create([
                        'commission_id' => $commission->id,
                        'type' => 'company',
                        'company_id' => $partner->company_id,
                        'amount' => round($commission->amount * $split['company'], 2),
                        'status' => 'pending',
                    ]);
                }

                CommissionDistribution::create([
                    'commission_id' => $commission->id,
                    'type' => 'partner',
                    'partner_id' => $partner->id,
                    'amount' => round($commission->amount * $split['partner'], 2),
                    'status' => 'pending',
                ]);
            }

            $order->update(['status' => OrderStatus::Paid]);

            return [$payment, $commission];
        });

        return response()->json([
            'message' => 'Payment completed',
            'payment' => $result[0],
            'commission' => $result[1]->load('distributions'),
            'order' => $order->fresh(),
        ], 201);
    }
}
