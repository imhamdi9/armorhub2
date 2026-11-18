<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\Payment;
use App\Models\Shipment;
use App\Models\User;
use App\Notifications\SimpleNotice;
use App\Support\Settings\Settings;
use Illuminate\Support\Facades\DB;

/**
 * A paid order must leave the seller in good time. After "remind" days without a parcel the seller is reminded; after "cancel" days the order is cancelled
 * and the customer is refunded in full (the seller was never paid for it, so nothing is taken from them). Orders ArmorHub ships itself are left to the warehouse.
 */
class ShippingDeadlines
{
    public function __construct(private Settings $settings, private OrderRefunds $refunds)
    {
    }

    /** @return array{reminded: int, cancelled: int} */
    public function run(): array
    {
        $remind = (int) $this->settings->get('orders.ship_remind_days');
        $cancel = (int) $this->settings->get('orders.ship_cancel_days');
        $out = ['reminded' => 0, 'cancelled' => 0];
        if ($remind <= 0 && $cancel <= 0) {
            return $out;
        }
        $actor = User::role('platform-admin')->first();

        Order::withoutGlobalScopes()->with('product:id,name,name_ar,seller_id,requires_shipping')->where('status', 'paid')->whereNull('refund_status')
            ->where(fn ($q) => $q->whereNull('fulfilment')->orWhere('fulfilment', '!=', 'armorhub'))
            ->whereHas('product', fn ($q) => $q->where('requires_shipping', true)->whereNotNull('seller_id'))
            ->get()->each(function (Order $o) use ($remind, $cancel, $actor, &$out) {
                if (Shipment::where('order_id', $o->id)->whereNotIn('status', [Shipment::CANCELLED])->exists()) {
                    return;                                                                                 // something is on its way
                }
                $paidAt = Payment::withoutGlobalScopes()->where('order_id', $o->id)->whereIn('status', ['completed', 'cod_due'])->min('created_at');
                if (! $paidAt) {
                    return;
                }
                $days = now()->diffInDays(\Carbon\Carbon::parse($paidAt), true);
                $name = $o->product?->name ?? 'your order';

                if ($cancel > 0 && $days >= $cancel && $actor) {
                    try {
                        DB::transaction(function () use ($o, $actor) {
                            $this->refunds->refund($o, $actor, 'The seller did not ship in time.');
                            OrderEvent::record($o->fresh(), 'auto_cancelled', null, ['reason' => 'not_shipped']);
                        });
                    } catch (\Throwable $e) {
                        report($e);                // one order that cannot be refunded must not stop the others

                        return;
                    }
                    User::find($o->customer_id)?->notify(new SimpleNotice('Your order was cancelled and refunded', ':name: the seller did not ship in time. Your money is on its way back.', "/dashboard/orders/{$o->id}", ['name' => (string) $name]));
                    $this->tellSeller($o, 'An order was cancelled because it was not shipped', "Order #{$o->id} ({$name}) was refunded to the customer.");
                    $out['cancelled']++;
                } elseif ($remind > 0 && $days >= $remind && ! $o->ship_reminded_at) {
                    $o->forceFill(['ship_reminded_at' => now()])->save();
                    $left = max(1, $cancel - (int) $days);
                    $this->tellSeller($o, 'Ship this order now', "Order #{$o->id} ({$name}) is paid and waiting. It is cancelled and refunded in {$left} day(s) if it does not ship.");
                    $out['reminded']++;
                }
            });

        return $out;
    }

    private function tellSeller(Order $o, string $title, string $body): void
    {
        User::where('company_id', $o->product?->seller_id)->where('is_active', true)->get()->each(fn (User $u) => $u->notify(new SimpleNotice($title, $body, '/dashboard/seller-orders')));
    }
}
