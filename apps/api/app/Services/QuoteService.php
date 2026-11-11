<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\QuoteStatus;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\User;
use App\Models\PartnerQuote;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuoteService
{
    /** Statuses in which a customer may still pick a quote. */
    public function accept(PartnerQuote $quote, ?User $by = null): Order
    {
        return DB::transaction(function () use ($quote, $by) {

            $order = Order::withoutGlobalScopes()->lockForUpdate()->findOrFail($quote->order_id);

            // Whether a quote may be picked now is the order workflow's decision (a tenant may have its own version).
            try {
                $planned = app(\App\Core\Workflow\WorkflowEngine::class)->plan(
                    Order::WORKFLOW, $order->status->value, 'accept_quote',
                    new \App\Core\Workflow\WorkflowContext($order, $by, \App\Models\Company::withoutGlobalScopes()->whereKey($order->company_id)->value('tenant_id')),
                );
            } catch (\App\Core\Workflow\TransitionNotAllowed $e) {
                throw ValidationException::withMessages(['quote' => [$e->getMessage()]]);
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

            $total = $order->product_amount + $quote->price + $order->commission_amount;

            $order->update([
                'service_amount' => $quote->price,
                'total_amount' => $total,
                'status' => $planned->to,
            ] + app(\App\Support\Markets\Markets::class)->taxFor(\App\Models\Company::withoutGlobalScopes()->find($order->company_id), (float) $total));

            OrderEvent::record($order, 'quote_accepted', $by, ['partner_id' => $quote->partner_id, 'price' => (string) $quote->price]);

            return $order->fresh();
        });
    }

    public function submit(PartnerQuote $quote, float|int|string $price, ?User $by = null): PartnerQuote
    {
        return DB::transaction(function () use ($quote, $price, $by) {

            $order = Order::withoutGlobalScopes()->findOrFail($quote->order_id);

            if (! $order->status->isOpenForQuotes()) {
                throw ValidationException::withMessages([
                    'quote' => ['This request is no longer open.'],
                ]);
            }

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

            OrderEvent::record($order, 'quote_submitted', $by, ['partner_id' => $quote->partner_id, 'price' => (string) $price]);

            return $quote->fresh();
        });
    }
}
