<?php

namespace App\Services;

use App\Models\Commission;
use App\Models\CommissionDistribution;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\Payment;
use App\Models\SellerLedgerEntry;
use App\Models\User;
use App\Services\Ledger\Accounts;
use App\Services\Ledger\Ledger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Giving back part of what a customer paid while the order stands (damage, a missing item, lateness). The platform and the seller give up the
 * same share of their cut as the share refunded; the seller's part is taken from their available balance (a negative entry, so it lowers
 * the next payout, exactly as a full refund after payout does). One partial refund per order, and never together with a full refund.
 * Two steps like a full refund: owed (recorded here), then sent and marked sent.
 */
class PartialRefunds
{
    public function __construct(private Ledger $ledger)
    {
    }

    public function refund(Order $order, User $by, float $amount, string $reason): Order
    {
        return DB::transaction(function () use ($order, $by, $amount, $reason) {
            $o = Order::withoutGlobalScopes()->lockForUpdate()->with('product:id,seller_id')->findOrFail($order->id);
            $amount = round($amount, 2);

            if ($o->refund_status || $o->partial_refund_status) {
                throw ValidationException::withMessages(['order' => ['This order was already refunded.']]);
            }
            $commission = Commission::where('order_id', $o->id)->first();
            if (! $commission || ! Payment::withoutGlobalScopes()->where('order_id', $o->id)->where('status', 'completed')->exists()) {
                throw ValidationException::withMessages(['order' => ['Only an order whose payment was received can be partly refunded.']]);
            }
            if (CommissionDistribution::where('commission_id', $commission->id)->whereIn('type', ['company', 'partner'])->where('amount', '>', 0)->exists()) {
                throw ValidationException::withMessages(['order' => ['This order shares its commission with a store or partner; refund it through support.']]);
            }
            $sellerId = $o->product?->seller_id;
            if (! $sellerId) {
                throw ValidationException::withMessages(['order' => ['Only a seller\'s product order can be refunded here.']]);
            }
            $payable = (float) $o->payable_amount;
            if ($amount < 0.01 || $amount >= $payable) {
                throw ValidationException::withMessages(['amount' => ["Enter an amount above zero and below what the customer paid ({$payable}). To give everything back, refund the order."]]);
            }

            // Every part gives up the same share of its cut; the seller takes the rounding remainder so the ledger balances to the cent.
            $r = $amount / $payable;
            $cur = $o->currency;
            $cut = round((float) $commission->amount * $r, 2);
            $fee = round((float) $o->service_fee_amount * $r, 2);
            $promo = round((float) $o->discount_amount * $r, 2);
            $seller = round($amount - $cut - $fee + $promo, 2);

            $this->ledger->post("order:{$o->id}:partial_refunded", 'order_partially_refunded', array_filter([
                [Accounts::sellerAvailable($sellerId), $cur, $seller],
                [Accounts::REV_COMMISSION, $cur, $cut],
                [Accounts::REV_SERVICE_FEE, $cur, $fee],
                [Accounts::EXP_PROMO, $cur, -$promo],
                [Accounts::REFUND_DUE, $cur, -$amount],
            ], fn ($l) => abs($l[2]) > 0), $o, "Order #{$o->id} partly refunded");

            $commission->update(['refunded_amount' => $cut]);
            SellerLedgerEntry::create(['company_id' => $sellerId, 'order_id' => $o->id, 'type' => 'partial_refund', 'amount' => -$seller, 'currency' => $cur, 'status' => 'available', 'available_at' => now()]);
            User::where('company_id', $sellerId)->role('seller')->get()->each(fn ($u) => $u->notify(new \App\Notifications\SimpleNotice(
                'Part of an order was refunded', 'Order #:id: :amount :currency was refunded to the customer and taken from your earnings.', '/dashboard/earnings',
                ['id' => (string) $o->id, 'amount' => (string) $seller, 'currency' => (string) $cur],
            )));

            $o->forceFill(['partial_refund_amount' => $amount, 'partial_refund_status' => 'pending', 'partial_refund_reason' => $reason, 'partial_refunded_at' => now()])->save();
            app(\App\Services\Loyalty::class)->partlyRefunded($o);
            OrderEvent::record($o, 'partially_refunded', $by, ['amount' => (string) $amount, 'reason' => $reason]);
            User::withoutGlobalScopes()->find($o->customer_id)?->notify(new \App\Notifications\SimpleNotice(
                'Part of your payment will be refunded', 'Order #:id: :amount :currency. :why', "/dashboard/orders/{$o->id}",
                ['id' => (string) $o->id, 'amount' => (string) $amount, 'currency' => (string) $cur, 'why' => $reason],
            ));

            return $o;
        });
    }

    /** The money went back to the customer. */
    public function markSent(Order $order, User $by, string $reference): Order
    {
        return DB::transaction(function () use ($order, $by, $reference) {
            $o = Order::withoutGlobalScopes()->lockForUpdate()->findOrFail($order->id);
            if ($o->partial_refund_status !== 'pending') {
                throw ValidationException::withMessages(['order' => ['There is no pending partial refund for this order.']]);
            }
            $amount = (float) $o->partial_refund_amount;
            $this->ledger->post("order:{$o->id}:partial_refund_paid", 'refund_paid', [
                [Accounts::REFUND_DUE, $o->currency, $amount],
                [Accounts::GATEWAY, $o->currency, -$amount],
            ], $o, "Partial refund of order #{$o->id} sent ({$reference})");
            $o->forceFill(['partial_refund_status' => 'refunded', 'partial_refund_reference' => $reference])->save();
            OrderEvent::record($o, 'partial_refund_sent', $by, ['reference' => $reference]);

            return $o;
        });
    }
}
