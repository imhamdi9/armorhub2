<?php

namespace App\Services\Ledger;

use App\Models\Commission;
use App\Models\CommissionDistribution;
use App\Models\Order;
use App\Models\InstallationRequest;
use App\Models\Partner;
use App\Models\SellerPayout;
use App\Models\CentreWithdrawal;
use App\Models\SubscriptionInvoice;

/**
 * What each business event does to the money. Called from the code that already handles the event, inside its transaction, so the books and the
 * records they describe can never disagree. Debits are positive, credits negative; every method balances to zero.
 */
class LedgerPostings
{
    public function __construct(private Ledger $ledger)
    {
    }

    /**
     * A customer paid an order. The gateway holds what they paid; it is owed to the seller (or the partner who does the job) less the commission,
     * the commission and the customer's platform fee are the platform's revenue.
     */
    public function orderPaid(Order $order, Commission $commission, ?Partner $partner = null, bool $cod = false): void
    {
        $cur = $order->currency;
        $sellerId = $order->product?->seller_id;
        $net = (float) $order->total_amount - (float) $commission->amount + (float) $order->shipping_amount;   // delivery is the seller's, with no commission
        $holder = $sellerId ? Accounts::sellerHeld($sellerId) : ($partner ? Accounts::servicePayable($partner->id) : null);

        $this->ledger->post("order:{$order->id}:paid", 'order_paid', array_filter([
            [$cod ? Accounts::COD_RECEIVABLE : Accounts::GATEWAY, $cur, (float) $order->payable_amount],   // pay on delivery: the courier owes it until delivery
            $holder ? [$holder, $cur, -$net] : [Accounts::REV_COMMISSION, $cur, -$net],   // no one to owe it to: the platform keeps it until someone is chosen
            [Accounts::REV_COMMISSION, $cur, -(float) $commission->amount],
            [Accounts::REV_SERVICE_FEE, $cur, -(float) $order->service_fee_amount],
            [Accounts::EXP_PROMO, $cur, (float) $order->discount_amount],     // the customer paid less: the platform bears the discount
        ]), $order, "Order #{$order->id} paid");

        // The host store's and the partner's shares of the commission are owed to them, out of the platform's commission.
        $lines = [];
        foreach (CommissionDistribution::where('commission_id', $commission->id)->whereIn('type', ['company', 'partner'])->get() as $d) {
            $owner = $d->type === 'company' ? $d->company_id : $d->partner_id;
            if (! $owner || (float) $d->amount <= 0) {
                continue;
            }
            $lines[] = [Accounts::REV_COMMISSION, $cur, (float) $d->amount];
            $lines[] = [Accounts::commissionPayable($d->type, (int) $owner), $cur, -(float) $d->amount];
        }
        if ($lines) {
            $this->ledger->post("order:{$order->id}:commission_shares", 'commission_shared', $lines, $order, "Commission shares of order #{$order->id}");
        }
    }

    /** The courier handed over the cash collected at the door. */
    public function codCollected(Order $order): void
    {
        $amount = (float) $order->payable_amount;
        $this->ledger->post("order:{$order->id}:cod_collected", 'cod_collected', [
            [Accounts::GATEWAY, $order->currency, $amount],
            [Accounts::COD_RECEIVABLE, $order->currency, -$amount],
        ], $order, "Cash on delivery collected for order #{$order->id}");
    }

    /** The goods arrived: what the seller is owed moves from held to available. */
    public function sellerReleased(Order $order): void
    {
        $sellerId = $order->product?->seller_id;
        $commission = Commission::where('order_id', $order->id)->first();
        if (! $sellerId || ! $commission) {
            return;
        }
        $net = (float) $order->total_amount - (float) $commission->amount + (float) $order->shipping_amount;

        $this->ledger->post("order:{$order->id}:released", 'seller_released', [
            [Accounts::sellerHeld($sellerId), $order->currency, $net],
            [Accounts::sellerAvailable($sellerId), $order->currency, -$net],
        ], $order, "Order #{$order->id} delivered");
    }

    public function payoutCreated(SellerPayout $p): void
    {
        $this->ledger->post("payout:{$p->id}:created", 'payout_created', [
            [Accounts::sellerAvailable($p->company_id), $p->currency, (float) $p->amount],
            [Accounts::payoutPending($p->company_id), $p->currency, -(float) $p->amount],
        ], $p, "Payout #{$p->id} approved");
    }

    public function payoutPaid(SellerPayout $p): void
    {
        $this->ledger->post("payout:{$p->id}:paid", 'payout_paid', [
            [Accounts::payoutPending($p->company_id), $p->currency, (float) $p->amount],
            [Accounts::GATEWAY, $p->currency, -(float) $p->amount],
        ], $p, "Payout #{$p->id} sent ({$p->reference})");
    }

    /** A customer paid for an installation: the centre's share is held, the rest is the platform's fee (after any loyalty discount). */
    public function installationPaid(InstallationRequest $r, int $centreCompany): void
    {
        $pay = (float) $r->pay_amount;
        $centre = (float) $r->centre_amount;

        $this->ledger->post("installation:{$r->id}:paid", 'installation_paid', [
            [Accounts::GATEWAY, $r->currency, $pay],
            [Accounts::centreHeld($centreCompany), $r->currency, -$centre],
            [Accounts::REV_INSTALLATION, $r->currency, -($pay - $centre)],
        ], $r, "Installation job #{$r->id} paid");
    }

    public function installationReleased(InstallationRequest $r, int $centreCompany): void
    {
        $centre = (float) $r->centre_amount;
        $this->ledger->post("installation:{$r->id}:released", 'installation_released', [
            [Accounts::centreHeld($centreCompany), $r->currency, $centre],
            [Accounts::centreAvailable($centreCompany), $r->currency, -$centre],
        ], $r, "Installation job #{$r->id} released");
    }

    /**
     * A dispute was decided against the centre, wholly or partly. `$old` is what was owed to the centre and `$bucket` where it sat ("held" or
     * "available"); `$new` is what the centre is owed now (0 for a full refund) and `$refund` what goes back to the customer.
     */
    public function installationRefunded(InstallationRequest $r, int $centreCompany, float $old, string $bucket, float $new, float $refund): void
    {
        $from = $bucket === 'held' ? Accounts::centreHeld($centreCompany) : Accounts::centreAvailable($centreCompany);
        // Whatever the centre and the customer do not account for comes out of (or back into) the platform's installation fee.
        $platform = $refund + $new - $old;

        $this->ledger->post("installation:{$r->id}:refunded", 'installation_refunded', [
            [$from, $r->currency, $old],
            [Accounts::centreAvailable($centreCompany), $r->currency, -$new],
            [Accounts::REV_INSTALLATION, $r->currency, $platform],
            [Accounts::REFUND_DUE, $r->currency, -$refund],
        ], $r, "Installation job #{$r->id} refunded");
    }

    public function installationRefundPaid(InstallationRequest $r): void
    {
        $refund = (float) $r->refund_amount;
        $this->ledger->post("installation:{$r->id}:refund_paid", 'refund_paid', [
            [Accounts::REFUND_DUE, $r->currency, $refund],
            [Accounts::GATEWAY, $r->currency, -$refund],
        ], $r, "Refund of installation job #{$r->id} sent ({$r->refund_reference})");
    }

    public function withdrawalRequested(CentreWithdrawal $w): void
    {
        $this->ledger->post("withdrawal:{$w->id}:requested", 'withdrawal_requested', [
            [Accounts::centreAvailable($w->company_id), $w->currency, (float) $w->amount],
            [Accounts::withdrawalPending($w->company_id), $w->currency, -(float) $w->amount],
        ], $w, "Withdrawal #{$w->id} asked for");
    }

    public function withdrawalPaid(CentreWithdrawal $w): void
    {
        $this->ledger->post("withdrawal:{$w->id}:paid", 'withdrawal_paid', [
            [Accounts::withdrawalPending($w->company_id), $w->currency, (float) $w->amount],
            [Accounts::GATEWAY, $w->currency, -(float) $w->amount],
        ], $w, "Withdrawal #{$w->id} sent ({$w->reference})");
    }

    public function withdrawalRejected(CentreWithdrawal $w): void
    {
        $this->ledger->post("withdrawal:{$w->id}:rejected", 'withdrawal_rejected', [
            [Accounts::withdrawalPending($w->company_id), $w->currency, (float) $w->amount],
            [Accounts::centreAvailable($w->company_id), $w->currency, -(float) $w->amount],
        ], $w, "Withdrawal #{$w->id} not paid");
    }

    public function subscriptionCharged(SubscriptionInvoice $i): void
    {
        $this->ledger->post("subscription_invoice:{$i->id}", 'subscription_charged', [
            [Accounts::GATEWAY, $i->currency, (float) $i->amount],
            [Accounts::REV_SUBSCRIPTION, $i->currency, -(float) $i->amount],
        ], $i, "Subscription {$i->plan_key} charged");
    }

    /** A commission share was paid out to the store or partner it belongs to. */
    public function commissionSharePaid(CommissionDistribution $d, string $currency): void
    {
        if (! in_array($d->type, ['company', 'partner'], true)) {
            return;
        }
        $owner = $d->type === 'company' ? $d->company_id : $d->partner_id;
        $this->ledger->post("commission_distribution:{$d->id}:paid", 'commission_share_paid', [
            [Accounts::commissionPayable($d->type, (int) $owner), $currency, (float) $d->amount],
            [Accounts::GATEWAY, $currency, -(float) $d->amount],
        ], $d, "Commission share #{$d->id} paid");
    }
}
