<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\QuoteStatus;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\PartnerQuote;
use App\Models\User;
use App\Models\Warranty;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * paid -> appointment_scheduled -> service_in_progress -> completed,
 * and cancellation before payment. Every move is validated, locked and logged.
 */
class OrderLifecycleService
{
    public function schedule(Order $order, Carbon $when, User $by): Order
    {
        return $this->move($order, [OrderStatus::Paid, OrderStatus::AppointmentScheduled], OrderStatus::AppointmentScheduled,
            'scheduled', $by, ['scheduled_at' => $when], ['scheduled_at' => $when->toIso8601String()]);
    }

    public function start(Order $order, User $by): Order
    {
        return $this->move($order, [OrderStatus::AppointmentScheduled], OrderStatus::ServiceInProgress,
            'started', $by, ['started_at' => now()]);
    }

    public function complete(Order $order, User $by): Order
    {
        return DB::transaction(function () use ($order, $by) {
            $order = $this->move($order, [OrderStatus::ServiceInProgress], OrderStatus::Completed,
                'completed', $by, ['completed_at' => now()]);

            $years = $order->product?->warranty_years;

            if ($years) {
                Warranty::withoutGlobalScopes()->firstOrCreate(['order_id' => $order->id], [
                    'company_id' => $order->company_id,
                    'customer_id' => $order->customer_id,
                    'product_id' => $order->product_id,
                    'starts_at' => now(),
                    'ends_at' => now()->addYears($years),
                ]);
            }

            return $order;
        });
    }

    public function cancel(Order $order, User $by, ?string $reason = null): Order
    {
        return DB::transaction(function () use ($order, $by, $reason) {
            $order = $this->move($order, array_filter(OrderStatus::cases(), fn ($s) => $s->isCancellable()),
                OrderStatus::Cancelled, 'cancelled', $by, ['cancelled_at' => now()], array_filter(['reason' => $reason]),
                'Paid orders cannot be cancelled here; please contact support.');

            PartnerQuote::withoutGlobalScopes()->where('order_id', $order->id)
                ->whereIn('status', [QuoteStatus::Pending, QuoteStatus::Submitted])
                ->update(['status' => QuoteStatus::Rejected]);

            return $order;
        });
    }

    /**
     * @param  list<OrderStatus>  $from
     */
    private function move(
        Order $order,
        array $from,
        OrderStatus $to,
        string $event,
        User $by,
        array $attributes = [],
        array $data = [],
        string $message = 'This step is not available for the order in its current state.'
    ): Order {
        return DB::transaction(function () use ($order, $from, $to, $event, $by, $attributes, $data, $message) {
            $locked = Order::withoutGlobalScopes()->with('product')->lockForUpdate()->findOrFail($order->id);

            if (! in_array($locked->status, $from, true)) {
                throw ValidationException::withMessages(['order' => [$message]]);
            }

            $locked->update(['status' => $to] + $attributes);
            OrderEvent::record($locked, $event, $by, $data);

            return $locked->fresh('product');
        });
    }
}
