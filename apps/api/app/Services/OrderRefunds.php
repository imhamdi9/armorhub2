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
            if (! $commission || ! \App\Models\Payment::withoutGlobalScopes()->where('order_id', $o->id)->whereIn('status', ['completed', 'cod_due'])->exists()) {
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
                $entries = SellerLedgerEntry::where('order_id', $o->id)->whereIn('type', ['sale', 'commission', 'shipping', 'discount'])->get();
                // Already paid out (or in a payout): the customer is refunded now and the seller owes it back, taken from what they earn next.
                $paidOut = $entries->contains(fn ($e) => $e->status === 'paid' || $e->payout_id !== null);
                $bucket = $paidOut || $entries->first()?->status === 'available' ? Accounts::sellerAvailable($sellerId) : Accounts::sellerHeld($sellerId);
            }

            $cur = $o->currency;
            $payable = (float) $o->payable_amount;

            // Part of this order was already refunded: only what is left goes back now, and each side gives up only what it still holds.
            $partial = $o->partial_refund_status ? (float) $o->partial_refund_amount : 0.0;
            $partialEntry = $partial > 0 ? SellerLedgerEntry::where('order_id', $o->id)->where('type', 'partial_refund')->first() : null;
            $r = $partial > 0 ? $partial / $payable : 0.0;
            $sellerPartial = $partialEntry ? round(-(float) $partialEntry->amount, 2) : 0.0;
            $cutPartial = $partial > 0 ? (float) $commission->refunded_amount : 0.0;
            $feePartial = round((float) $o->service_fee_amount * $r, 2);
            $promoPartial = round((float) $o->discount_amount * $r, 2);
            // Paid on delivery and not yet delivered: no money was taken, so nothing goes back.
            $codDue = \App\Models\Payment::withoutGlobalScopes()->where('order_id', $o->id)->where('status', 'cod_due')->first();
            $sellerDiscount = (float) $o->seller_discount_amount;
            $net = (float) $o->total_amount - (float) $commission->amount + (float) $o->shipping_amount;

            $this->ledger->post("order:{$o->id}:refunded", 'order_refunded', array_filter([
                // The seller's share of an earlier partial refund was taken from their available balance: give it back when the earnings leave from "held".
                $sellerId ? [$bucket, $cur, $paidOut ?? false ? $net - $sellerPartial : $net] : [Accounts::REV_COMMISSION, $cur, $net],
                $sellerId && $sellerPartial > 0 && ! ($paidOut ?? false) ? [Accounts::sellerAvailable($sellerId), $cur, -$sellerPartial] : null,
                $sellerDiscount > 0 ? [$bucket, $cur, -$sellerDiscount] : null,              // the seller gets back what its own coupon cost it
                $sellerDiscount > 0 ? [Accounts::EXP_PROMO, $cur, $sellerDiscount] : null,
                [Accounts::REV_COMMISSION, $cur, (float) $commission->amount - $cutPartial],
                [Accounts::REV_SERVICE_FEE, $cur, (float) $o->service_fee_amount - $feePartial],
                [Accounts::EXP_PROMO, $cur, -((float) $o->discount_amount - $promoPartial)],        // the discount is no longer the platform's cost
                [$codDue ? Accounts::COD_RECEIVABLE : Accounts::REFUND_DUE, $cur, -($payable - $partial)],
            ]), $o, "Order #{$o->id} refunded");

            if ($paidOut ?? false) {
                // What the seller already received becomes a debt: it lowers their available balance until it is covered by later sales.
                \App\Models\SellerLedgerEntry::create(['company_id' => $sellerId, 'order_id' => $o->id, 'type' => 'refund_debt', 'amount' => -round((float) $entries->sum('amount') - $sellerPartial, 2), 'currency' => $cur, 'status' => 'available']);
                \App\Models\User::where('company_id', $sellerId)->role('seller')->get()->each(fn ($u) => $u->notify(new \App\Notifications\SimpleNotice('An order you were paid for was refunded', "Order #{$o->id}: the amount will be taken from your next earnings.", '/dashboard/earnings')));
            } else {
                foreach ($entries as $e) {
                    $e->update(['status' => 'refunded']);
                }
                $partialEntry?->update(['status' => 'refunded']);
            }
            $commission->update(['status' => 'cancelled']);
            CommissionDistribution::where('commission_id', $commission->id)->update(['status' => 'cancelled']);

            $codDue?->update(['status' => 'void']);
            $o->forceFill(['refund_amount' => round($payable - $partial, 2), 'refund_status' => $codDue ? 'refunded' : 'pending', 'refund_reason' => $reason, 'refunded_at' => now()])->save();
            OrderEvent::record($o, 'refunded', $by, ['amount' => (string) round($payable - $partial, 2), 'reason' => $reason]);
            app(\App\Services\Loyalty::class)->refunded($o);

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
