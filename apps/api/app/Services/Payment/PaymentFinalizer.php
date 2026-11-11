<?php

namespace App\Services\Payment;

use App\Enums\OrderStatus;
use App\Enums\QuoteStatus;
use App\Models\Commission;
use App\Models\CommissionDistribution;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\Partner;
use App\Models\PartnerQuote;
use App\Models\Payment;
use App\Models\User;
use App\Services\Commission\CalculateCommissionService;
use App\Support\Settings\Settings;
use Illuminate\Support\Facades\DB;

/**
 * Everything that happens once money has really been received: the payment is
 * recorded, the commission is split, the order becomes paid. Idempotent, so a
 * webhook delivered twice (or racing the browser) pays an order exactly once.
 */
class PaymentFinalizer
{
    public function __construct(private CalculateCommissionService $commissions, private Settings $settings)
    {
    }

    /**
     * @param  array{transaction_id: ?string}  $charge
     * @return array{Payment, Commission}|null  null when the order was already paid
     */
    public function complete(Order $order, array $charge, ?string $method = null, ?User $by = null): ?array
    {
        return DB::transaction(function () use ($order, $charge, $method, $by) {

            $locked = Order::withoutGlobalScopes()->lockForUpdate()->findOrFail($order->id);

            if ($locked->status === OrderStatus::Paid) {
                return null;
            }

            // A pending payment created when checkout started is completed, not duplicated.
            $payment = Payment::withoutGlobalScopes()
                ->where('order_id', $locked->id)
                ->when($charge['transaction_id'] ?? null, fn ($q, $id) => $q->where('transaction_id', $id))
                ->where('status', 'pending')
                ->first();

            $attributes = [
                'amount' => $locked->total_amount,
                'currency' => $locked->currency,
                'payment_method' => $method ?? $payment?->payment_method,
                'status' => 'completed',
                'transaction_id' => $charge['transaction_id'] ?? null,
            ];

            $payment = $payment
                ? tap($payment)->update($attributes)
                : Payment::create($attributes + ['company_id' => $locked->company_id, 'order_id' => $locked->id]);

            $calc = $this->commissions->calculate($locked->total_amount);

            $commission = Commission::create([
                'order_id' => $locked->id,
                'amount' => $calc['amount'],
                'percentage' => $calc['percentage'],
                'status' => 'pending',
            ]);

            $quote = PartnerQuote::withoutGlobalScopes()
                ->where('order_id', $locked->id)
                ->where('status', QuoteStatus::Accepted)
                ->first();

            $partner = $quote ? Partner::withoutGlobalScopes()->find($quote->partner_id) : null;

            if ($partner) {
                $this->distribute($commission, $partner);
            }

            $locked->update(['status' => OrderStatus::Paid]);
            OrderEvent::record($locked, 'paid', $by, ['amount' => (string) $locked->total_amount]);

            return [$payment, $commission];
        });
    }

    private function distribute(Commission $commission, Partner $partner): void
    {
        $split = [
            'platform' => (float) $this->settings->get('platform.commission_split.platform'),
            'company' => (float) $this->settings->get('platform.commission_split.company'),
            'partner' => (float) $this->settings->get('platform.commission_split.partner'),
        ];

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
}
