<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A customer who wants to cancel a paid order or return it. The rules are the platform's, so no one has to negotiate:
 *  - nothing has left the seller yet: the order is cancelled and the whole amount is refunded at once;
 *  - it has shipped, or arrived within the return window: the request goes to ArmorHub, which decides (and the seller is not paid until it does).
 * Services and installation jobs have their own protections (proof, objection window, disputes) and are not handled here.
 */
class OrderRefundRequests
{
    /** Days after delivery during which a customer may still ask to return goods. */
    public const RETURN_DAYS = 14;

    public function __construct(private OrderRefunds $refunds)
    {
    }

    /** @return array{status: string, kind: string, id: int} */
    public function request(Order $order, User $customer, string $reason): array
    {
        return DB::transaction(function () use ($order, $customer, $reason) {
            $o = Order::withoutGlobalScopes()->lockForUpdate()->with('product:id,requires_shipping,seller_id')->findOrFail($order->id);

            if ($o->customer_id !== $customer->id) {
                abort(404);
            }
            if (! $o->product?->requires_shipping) {
                throw ValidationException::withMessages(['order' => ['Services are cancelled or disputed from the job itself.']]);
            }
            if ($o->refund_status) {
                throw ValidationException::withMessages(['order' => ['A refund was already made for this order.']]);
            }
            if (! \App\Models\Payment::withoutGlobalScopes()->where('order_id', $o->id)->where('status', 'completed')->exists()) {
                throw ValidationException::withMessages(['order' => ['This order is not paid. Cancel it instead.']]);
            }
            if (\App\Models\OrderRefundRequest::where('order_id', $o->id)->where('status', 'requested')->exists()) {
                throw ValidationException::withMessages(['order' => ['You already asked for a refund. We will answer soon.']]);
            }

            $shipments = Shipment::where('order_id', $o->id)->whereNotIn('status', [Shipment::CANCELLED])->get();
            $left = $shipments->contains(fn ($s) => in_array($s->status, [Shipment::IN_TRANSIT, Shipment::OUT_FOR_DELIVERY, Shipment::DELIVERED, Shipment::RETURNED, Shipment::FAILED], true));

            if ($shipments->contains(fn ($s) => $s->status === Shipment::DELIVERED)) {
                $deliveredAt = $shipments->where('status', Shipment::DELIVERED)->map(fn ($s) => $s->delivered_at ?? $s->updated_at)->max();
                if ($deliveredAt && now()->diffInDays($deliveredAt, true) > self::RETURN_DAYS) {
                    throw ValidationException::withMessages(['order' => ['The '.self::RETURN_DAYS.'-day return period for this order has ended.']]);
                }
            }

            $req = \App\Models\OrderRefundRequest::create(['order_id' => $o->id, 'customer_id' => $customer->id, 'reason' => $reason, 'kind' => $left ? 'return' : 'cancel']);
            OrderEvent::record($o, 'refund_requested', $customer, ['reason' => $reason, 'kind' => $req->kind]);

            if (! $left) {
                try {
                    $this->approve($req, $customer, 'Cancelled before shipping.');
                } catch (ValidationException) {
                    // The seller was already paid (or the commission is shared): a person has to settle it.
                    $req->refresh();
                }
            }

            return ['id' => $req->id, 'kind' => $req->kind, 'status' => $req->fresh()->status];
        });
    }

    public function approve(\App\Models\OrderRefundRequest $req, User $by, ?string $note = null): \App\Models\OrderRefundRequest
    {
        return DB::transaction(function () use ($req, $by, $note) {
            $r = \App\Models\OrderRefundRequest::lockForUpdate()->findOrFail($req->id);
            if ($r->status !== 'requested') {
                throw ValidationException::withMessages(['request' => ['This request was already decided.']]);
            }
            $order = Order::withoutGlobalScopes()->findOrFail($r->order_id);
            $this->refunds->refund($order, $by, $r->reason);
            // Anything not yet on its way is stopped: no one should ship what was paid back.
            Shipment::where('order_id', $order->id)->where('status', Shipment::CREATED)->update(['status' => Shipment::CANCELLED]);
            $r->update(['status' => 'approved', 'decided_by' => $by->id, 'decided_at' => now(), 'decision_note' => $note]);

            return $r;
        });
    }

    public function reject(\App\Models\OrderRefundRequest $req, User $by, string $note): \App\Models\OrderRefundRequest
    {
        if ($req->status !== 'requested') {
            throw ValidationException::withMessages(['request' => ['This request was already decided.']]);
        }
        $req->update(['status' => 'rejected', 'decided_by' => $by->id, 'decided_at' => now(), 'decision_note' => $note]);
        OrderEvent::record(Order::withoutGlobalScopes()->findOrFail($req->order_id), 'refund_rejected', $by, ['note' => $note]);

        return $req;
    }
}
