<?php

namespace App\Services;

use App\Enums\QuoteStatus;
use App\Models\Order;
use App\Models\PartnerQuote;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\Settings\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Monthly plans for service partners and sellers. A plan sets how many customers a month the business can serve, the commission taken from
 * its orders and how many team seats it has. Everyone starts on the free plan. Paid plans are charged through the payment gateway in use;
 * with the simulated gateway the charge is recorded as simulated.
 */
class SubscriptionService
{
    public const FREE = 'free';

    public function __construct(private Settings $settings)
    {
    }

    /** The business a signed-in person manages: their partner, or their seller company; null for customers and staff. @return array{0: string, 1: int}|null */
    public function subjectOf(User $user): ?array
    {
        if ($user->partner_id !== null && $user->hasRole('partner-manager')) {
            return ['partner', (int) $user->partner_id];
        }
        if ($user->hasRole('seller') && $user->company_id !== null) {
            return ['seller', (int) $user->company_id];
        }

        return null;
    }

    public function plans()
    {
        return SubscriptionPlan::orderBy('sort')->get();
    }

    public function subscriptionFor(string $type, int $id): Subscription
    {
        $sub = Subscription::firstOrCreate(['subject_type' => $type, 'subject_id' => $id], ['plan_key' => self::FREE, 'status' => 'active', 'auto_renew' => true]);

        // A paid plan whose month has ended without renewal falls back to the free plan.
        if ($sub->plan_key !== self::FREE && $sub->period_end !== null && $sub->period_end->isPast() && ! $sub->period_end->isToday()) {
            $sub->update(['plan_key' => self::FREE, 'period_start' => null, 'period_end' => null]);
        }

        return $sub;
    }

    public function planFor(string $type, int $id): SubscriptionPlan
    {
        $key = $this->subscriptionFor($type, $id)->plan_key;

        return SubscriptionPlan::where('key', $key)->first() ?? SubscriptionPlan::where('key', self::FREE)->firstOrFail();
    }

    /** Customers served this calendar month: distinct customers (or guest orders) behind the business's orders. */
    public function customersThisMonth(string $type, int $id): int
    {
        $from = now()->startOfMonth();

        $orders = Order::withoutGlobalScopes()->where('created_at', '>=', $from)->where('status', '!=', 'cancelled');

        if ($type === 'partner') {
            $orders->whereIn('id', PartnerQuote::withoutGlobalScopes()->where('partner_id', $id)->where('status', QuoteStatus::Accepted)->select('order_id'));
        } else {
            $orders->whereIn('product_id', Product::withoutGlobalScopes()->where('seller_id', $id)->select('id'));
        }

        return $orders->get(['id', 'customer_id', 'guest_email'])->map(fn ($o) => $o->customer_id ? 'c'.$o->customer_id : 'g'.($o->guest_email ?: $o->id))->unique()->count();
    }

    /** Whether the business has used up its customers for the month. */
    public function limitReached(string $type, int $id): bool
    {
        $limit = $this->planFor($type, $id)->customer_limit;

        return $limit !== null && $this->customersThisMonth($type, $id) >= $limit;
    }

    public function assertCanTakeCustomers(string $type, int $id): void
    {
        if ($this->limitReached($type, $id)) {
            throw ValidationException::withMessages(['plan' => ['This month\'s customer limit of your plan has been reached. Upgrade your plan to take more customers.']]);
        }
    }

    /** The commission percentage for an order: the plan of the partner or seller behind it, else the platform default. */
    public function commissionPercentageFor(Order $order): float
    {
        $default = (float) $this->settings->get('platform.commission_percentage');

        $partnerId = PartnerQuote::withoutGlobalScopes()->where('order_id', $order->id)->where('status', QuoteStatus::Accepted)->value('partner_id');
        if ($partnerId) {
            return (float) $this->planFor('partner', (int) $partnerId)->commission_percentage;
        }

        $sellerId = Product::withoutGlobalScopes()->whereKey($order->product_id)->value('seller_id');
        if ($sellerId) {
            return (float) $this->planFor('seller', (int) $sellerId)->commission_percentage;
        }

        return $default;
    }

    /** Move a business to a plan. Free is instant; a paid plan is charged for its first month now. */
    public function choose(string $type, int $id, string $planKey, ?string $paidReference = null): Subscription
    {
        $plan = SubscriptionPlan::where('key', $planKey)->where('is_active', true)->first();
        if (! $plan) {
            throw ValidationException::withMessages(['plan' => ['That plan is not available.']]);
        }

        return DB::transaction(function () use ($type, $id, $plan, $paidReference) {
            $sub = $this->subscriptionFor($type, $id);

            if ($plan->key === self::FREE) {
                $sub->update(['plan_key' => self::FREE, 'status' => 'active', 'period_start' => null, 'period_end' => null, 'auto_renew' => true]);

                return $sub->fresh();
            }

            $this->charge($sub, $plan, now()->toDateString(), $paidReference);

            return $sub->fresh();
        });
    }

    /** Stop renewing: the plan stays until the month ends, then falls back to the free plan. */
    public function cancel(string $type, int $id): Subscription
    {
        $sub = $this->subscriptionFor($type, $id);
        $sub->update(['auto_renew' => false]);

        return $sub->fresh();
    }

    /** Renews every paid plan whose month has ended, or moves it to the free plan when it was cancelled. Returns [renewed, ended]. @return array{0: int, 1: int} */
    public function renewDue(): array
    {
        $renewed = $ended = 0;

        Subscription::where('plan_key', '!=', self::FREE)->whereNotNull('period_end')->whereDate('period_end', '<=', now()->toDateString())->get()->each(function (Subscription $sub) use (&$renewed, &$ended) {
            $plan = SubscriptionPlan::where('key', $sub->plan_key)->where('is_active', true)->first();

            if ($sub->auto_renew && $plan) {
                $this->charge($sub, $plan, $sub->period_end->copy()->toDateString());
                $renewed++;
            } else {
                $sub->update(['plan_key' => self::FREE, 'period_start' => null, 'period_end' => null, 'auto_renew' => true]);
                $ended++;
            }
        });

        return [$renewed, $ended];
    }

    private function charge(Subscription $sub, SubscriptionPlan $plan, string $start, ?string $paidReference = null): void
    {
        $from = \Illuminate\Support\Carbon::parse($start);
        $to = $from->copy()->addMonth();

        // The payment provider in use charges the business; the simulated one records a simulated charge. A real provider plugs in here.
        $invoice = SubscriptionInvoice::create([
            'subscription_id' => $sub->id, 'plan_key' => $plan->key, 'amount' => $plan->price_monthly, 'currency' => $plan->currency, 'status' => 'paid',
            'period_start' => $from->toDateString(), 'period_end' => $to->toDateString(), 'reference' => $paidReference ?? 'simulated-'.bin2hex(random_bytes(4)), 'paid_at' => now(),
        ]);

        app(\App\Services\Ledger\LedgerPostings::class)->subscriptionCharged($invoice);

        $sub->update(['plan_key' => $plan->key, 'status' => 'active', 'period_start' => $from->toDateString(), 'period_end' => $to->toDateString(), 'auto_renew' => true]);
    }
}
