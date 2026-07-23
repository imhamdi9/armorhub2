<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Commission;
use App\Models\CommissionDistribution;
use App\Models\InstallationQuote;

class PaymentController extends Controller
{
    public function store(Request $request, Order $order)
    {
        $data = $request->validate([
            'amount' => 'required|numeric',
            'currency' => 'nullable|string',
            'payment_method' => 'nullable|string',
        ]);

        // منع تكرار الدفع
        if ($order->status === 'paid') {
            return response()->json([
                'message' => 'Order already paid'
            ], 400);
        }

        $payment = Payment::create([
            'order_id' => $order->id,
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? 'USD',
            'payment_method' => $data['payment_method'] ?? null,
            'status' => 'completed',
        ]);

        $commissionPercentage = 10;

        $commissionAmount = $order->total_amount * ($commissionPercentage / 100);

        $commission = Commission::create([
            'order_id' => $order->id,
            'amount' => $commissionAmount,
            'percentage' => $commissionPercentage,
            'status' => 'pending',
        ]);

        $quote = InstallationQuote::where('order_id', $order->id)
            ->where('status', 'accepted')
            ->first();

        if ($quote && $quote->installationCenter) {

            $center = $quote->installationCenter;

            // Platform 35%
            CommissionDistribution::create([
                'commission_id' => $commission->id,
                'type' => 'platform',
                'amount' => $commission->amount * 0.35,
                'status' => 'pending',
            ]);

            // Company 25%
            if ($center->company_id) {
                CommissionDistribution::create([
                    'commission_id' => $commission->id,
                    'type' => 'company',
                    'company_id' => $center->company_id,
                    'amount' => $commission->amount * 0.25,
                    'status' => 'pending',
                ]);
            }

            // Installation Center 40%
            CommissionDistribution::create([
                'commission_id' => $commission->id,
                'type' => 'installation_center',
                'installation_center_id' => $center->id,
                'amount' => $commission->amount * 0.40,
                'status' => 'pending',
            ]);
        }

        $order->update([
            'status' => 'paid',
        ]);

        return response()->json([
            'message' => 'Payment completed',
            'payment' => $payment,
            'commission' => $commission->load('distributions'),
            'order' => $order,
        ], 201);
    }
}