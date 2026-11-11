<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\QuoteStatus;
use App\Models\Order;
use App\Models\PartnerQuote;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuoteService
{
    /** Statuses in which a customer may still pick a quote. */
    private const OPEN = [OrderStatus::PendingQuotes, OrderStatus::QuotesReceived];

    public function accept(PartnerQuote $quote): Order
    {
        return DB::transaction(function () use ($quote) {

            $order = Order::withoutGlobalScopes()->lockForUpdate()->findOrFail($quote->order_id);

            if (! in_array($order->status, self::OPEN, true)) {
                throw ValidationException::withMessages([
                    'quote' => ['A quote has already been selected for this order.'],
                ]);
            }

            if ($quote->status !== QuoteStatus::Submitted) {
                throw ValidationException::withMessages([
                    'quote' => ['Only a submitted quote can be accepted.'],
                ]);
            }

            PartnerQuote::withoutGlobalScopes()
                ->where('order_id', $order->id)
                ->where('id', '!=', $quote->id)
                ->update(['status' => QuoteStatus::Rejected]);

            $quote->update(['status' => QuoteStatus::Accepted]);

            $order->update([
                'service_amount' => $quote->price,
                'total_amount' => $order->product_amount + $quote->price + $order->commission_amount,
                'status' => OrderStatus::PartnerSelected,
            ]);

            return $order->fresh();
        });
    }

    public function submit(PartnerQuote $quote, float|int|string $price): PartnerQuote
    {
        return DB::transaction(function () use ($quote, $price) {

            if (! in_array($quote->status, [QuoteStatus::Pending, QuoteStatus::Submitted], true)) {
                throw ValidationException::withMessages([
                    'quote' => ['This quote can no longer be changed.'],
                ]);
            }

            $quote->update([
                'price' => $price,
                'status' => QuoteStatus::Submitted,
                'submitted_at' => now(),
            ]);

            Order::withoutGlobalScopes()
                ->where('id', $quote->order_id)
                ->where('status', OrderStatus::PendingQuotes)
                ->update(['status' => OrderStatus::QuotesReceived]);

            return $quote->fresh();
        });
    }
}
