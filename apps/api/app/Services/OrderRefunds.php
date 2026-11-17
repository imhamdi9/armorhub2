<?php

namespace App\Services;

use App\Models\Commission;
use App\Models\CommissionDistribution;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\SellerLedgerEntry;
use App\Models\User;
use App\Services\Ledger\Accounts;
use App\Services\Ledger\Ledger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Giving a customer their money back for a paid product order. The whole amount goes back; the seller's share is taken out of what they are
 * owed (held or available, never once it is in a payout) and the platform gives up its commission and fee. Two steps, as with installation jobs:
 * the refund is owed (recorded here), then it is sent through the payment provider and marked sent.
 */
class OrderRefunds
{
    public function __construct(private Ledger $ledger)
    {
    }

    public function refund(Order $order, User $by, string $reason): Order
    {
        return DB::transaction(function () use ($order, $by, $reason) {
            $o = Order::withoutGlobalScopes()->lockForUpdate()->with('product:id,seller_id')->findOrFail($order->id);

            if ($o->refund_status) {
                throw ValidationException::withMessages(['order' => ['This order was already refunded.']]);
            }
            $commission = Commission::where('order_id', $o->id)->first();
            if (! $commission || ! \App\Models\Payment::withoutGlobalScopes()->where('order_id', $o->id)->where('status', 'completed')->exists()) {
                throw ValidationException::withMessages(['order' => ['Only a paid order can be refunded.']]);
            }
            if (CommissionDistribution::where('commission_id', $commission->id)->whereIn('type', ['company', 'partner'])->where('amount', '>', 0)->exists()) {
                throw ValidationException::withMessages(['order' => ['This order shares its commission with a store or partner; refund it through support.']]);
            }

            $sellerId = $o->product?->seller_id;
            if (! $sellerId) {
                throw ValidationException::withMessages(['order' => ['Only a seller\'s product order can be refunded here.']]);
            }
            $bucket = null;
            $entries = collect();
            if ($sellerId) {
                $entries = SellerLedgerEntry::where('order_id', $o->id)->whereIn('type', ['sale', 'commission'])->get();
                if ($entries->contains(fn ($e) => ! in_array($e->status, ['held', 'available'], true) || $e->payout_id !== null)) {
                    throw ValidationException::withMessages(['order' => ['The seller was already paid for this order; settle it with the seller first.']]);
                }
                $bucket = $entries->first()?->status === 'available' ? Accounts::sellerAvailable($sellerId) : Accounts::sellerHeld($sellerId);
            }

            $cur = $o->currency;
            $payable = (float) $o->payable_amount;
            $net = (float) $o->total_amount - (float) $commission->amount;

            $this->ledger->post("order:{$o->id}:refunded", 'order_refunded', array_filter([
                $sellerId ? [$bucket, $cur, $net] : [Accounts::REV_COMMISSION, $cur, $net],
                [Accounts::REV_COMMISSION, $cur, (float) $commission->amount],
                [Accounts::REV_SERVICE_FEE, $cur, (float) $o->service_fee_amount],
                [Accounts::REFUND_DUE, $cur, -$payable],
            ]), $o, "Order #{$o->id} refunded");

            foreach ($entries as $e) {
                $e->update(['status' => 'refunded']);
            }
            $commission->update(['status' => 'cancelled']);
            CommissionDistribution::where('commission_id', $commission->id)->update(['status' => 'cancelled']);

            $o->forceFill(['refund_amount' => $payable, 'refund_status' => 'pending', 'refund_reason' => $reason, 'refunded_at' => now()])->save();
            OrderEvent::record($o, 'refunded', $by, ['amount' => (string) $payable, 'reason' => $reason]);

            return $o;
        });
    }

    /** The money went back to the customer through the payment provider. */
    public function markSent(Order $order, User $by, string $reference): Order
    {
        return DB::transaction(function () use ($order, $by, $reference) {
            $o = Order::withoutGlobalScopes()->lockForUpdate()->findOrFail($order->id);
            if ($o->refund_status !== 'pending') {
                throw ValidationException::withMessages(['order' => ['There is no pending refund for this order.']]);
            }
            $refund = (float) $o->refund_amount;
            $this->ledger->post("order:{$o->id}:refund_paid", 'refund_paid', [
                [Accounts::REFUND_DUE, $o->currency, $refund],
                [Accounts::GATEWAY, $o->currency, -$refund],
            ], $o, "Refund of order #{$o->id} sent ({$reference})");
            $o->forceFill(['refund_status' => 'refunded', 'refund_reference' => $reference])->save();
            OrderEvent::record($o, 'refund_sent', $by, ['reference' => $reference]);

            return $o;
        });
    }
}
