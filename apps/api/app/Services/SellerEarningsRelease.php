<?php

namespace App\Services;

use App\Models\Order;
use App\Models\SellerLedgerEntry;
use App\Services\Ledger\LedgerPostings;
use App\Support\Settings\Settings;
use Illuminate\Support\Facades\DB;

/**
 * What a seller earns on a delivered order is unlocked at once, or after a number of days the market chooses (setting payouts.hold_days:
 * 0 = at once). The days give the return window time to pass before the money can be paid out.
 */
class SellerEarningsRelease
{
    public function __construct(private Settings $settings, private LedgerPostings $postings)
    {
    }

    /** The order was delivered: unlock now, or schedule the unlock. */
    public function delivered(Order $order): void
    {
        $tenant = \App\Models\Company::withoutGlobalScopes()->whereKey($order->company_id)->value('tenant_id');
        $days = (int) $this->settings->inMarket($tenant ? (int) $tenant : null, fn () => $this->settings->get('payouts.hold_days'));

        if ($days <= 0) {
            $this->release($order);

            return;
        }
        SellerLedgerEntry::where('order_id', $order->id)->where('status', 'held')->whereNull('release_at')->update(['release_at' => now()->addDays($days)]);
    }

    /** Unlocks every delivered order whose waiting time is over. @return int orders released */
    public function run(): int
    {
        $released = 0;
        $due = SellerLedgerEntry::where('status', 'held')->whereNotNull('release_at')->where('release_at', '<=', now())->pluck('order_id')->unique();
        foreach ($due as $orderId) {
            $order = Order::withoutGlobalScopes()->with('product:id,seller_id')->find($orderId);
            if (! $order) {
                continue;
            }
            try {
                $this->release($order);
                $released++;
            } catch (\Throwable $e) {
                report($e);                        // one order that cannot be released must not hold back the rest
            }
        }

        return $released;
    }

    private function release(Order $order): void
    {
        DB::transaction(function () use ($order) {
            SellerLedgerEntry::where('order_id', $order->id)->where('status', 'held')->update(['status' => 'available', 'available_at' => now(), 'release_at' => null]);
            $this->postings->sellerReleased($order);
        });
    }
}
