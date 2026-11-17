<?php

namespace App\Services\Ledger;

use App\Enums\QuoteStatus;
use App\Models\CentreWithdrawal;
use App\Models\Commission;
use App\Models\CommissionDistribution;
use App\Models\InstallationOffer;
use App\Models\InstallationRequest;
use App\Models\Order;
use App\Models\Partner;
use App\Models\PartnerQuote;
use App\Models\SellerLedgerEntry;
use App\Models\SellerPayout;
use App\Models\SubscriptionInvoice;

/**
 * Brings the ledger up to date with money that moved before the ledger existed. Safe to run again: every posting has an idempotency key.
 * What cannot be rebuilt from today's records (an installation whose price was cut by a partial refund) is listed, not guessed.
 */
class LedgerBackfill
{
    public function __construct(private LedgerPostings $post)
    {
    }

    /** @return array{posted: int, skipped: list<string>} */
    public function run(): array
    {
        $skipped = [];
        $count = 0;

        foreach (Commission::all() as $commission) {
            $order = Order::withoutGlobalScopes()->with('product')->find($commission->order_id);
            if (! $order) {
                continue;
            }
            $quote = PartnerQuote::withoutGlobalScopes()->where('order_id', $order->id)->where('status', QuoteStatus::Accepted)->first();
            $partner = $quote ? Partner::withoutGlobalScopes()->find($quote->partner_id) : null;
            $this->post->orderPaid($order, $commission, $partner);
            $count++;

            if (SellerLedgerEntry::where('order_id', $order->id)->whereIn('status', ['available', 'paid'])->exists()) {
                $this->post->sellerReleased($order);
            }
        }

        foreach (SellerPayout::all() as $payout) {
            $this->post->payoutCreated($payout);
            $payout->status === 'paid' && $this->post->payoutPaid($payout);
            $count++;
        }

        foreach (InstallationRequest::whereNotNull('paid_at')->whereNotNull('selected_offer_id')->get() as $r) {
            if ($r->refund_amount !== null && (float) $r->refund_amount > 0 && $r->status === 'released') {
                $skipped[] = "Installation job #{$r->id}: a partial refund changed its price, so its history cannot be rebuilt. Review it by hand.";
                continue;
            }
            $centre = Partner::withoutGlobalScopes()->find(InstallationOffer::find($r->selected_offer_id)?->partner_id);
            if (! $centre?->company_id) {
                continue;
            }
            $this->post->installationPaid($r, (int) $centre->company_id);
            $count++;
            if ($r->released_at) {
                $this->post->installationReleased($r, (int) $centre->company_id);
            }
            if ($r->status === 'refunded') {
                $this->post->installationRefunded($r, (int) $centre->company_id, (float) $r->centre_amount, $r->released_at ? 'available' : 'held', 0.0, (float) $r->pay_amount);
                $r->refund_status === 'refunded' && $this->post->installationRefundPaid($r);
            }
        }

        foreach (CentreWithdrawal::all() as $w) {
            $this->post->withdrawalRequested($w);
            $w->status === 'paid' && $this->post->withdrawalPaid($w);
            $w->status === 'rejected' && $this->post->withdrawalRejected($w);
            $count++;
        }

        foreach (SubscriptionInvoice::where('status', 'paid')->get() as $invoice) {
            $this->post->subscriptionCharged($invoice);
            $count++;
        }

        foreach (CommissionDistribution::where('status', 'paid')->get() as $d) {
            $currency = Order::withoutGlobalScopes()->whereKey(Commission::whereKey($d->commission_id)->value('order_id'))->value('currency');
            $currency && $this->post->commissionSharePaid($d, $currency);
        }

        return ['posted' => $count, 'skipped' => $skipped];
    }
}
