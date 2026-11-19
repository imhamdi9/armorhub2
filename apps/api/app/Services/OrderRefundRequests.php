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
            if (! \App\Models\Payment::withoutGlobalScopes()->where('order_id', $o->id)->whereIn('status', ['completed', 'cod_due'])->exists()) {
                throw ValidationException::withMessages(['order' => ['This order is not paid. Cancel it instead.']]);
            }
            if (\App\Models\OrderRefundRequest::where('order_id', $o->id)->whereIn('status', ['requested', 'awaiting_pickup', 'collected'])->exists()) {
                throw ValidationException::withMessages(['order' => ['You already asked for a refund. We will answer soon.']]);
            }

            $shipments = Shipment::where('order_id', $o->id)->whereNotIn('status', [Shipment::CANCELLED])->get();
            $left = $shipments->contains(fn ($s) => in_array($s->status, [Shipment::IN_TRANSIT, Shipment::OUT_FOR_DELIVERY, Shipment::DELIVERED, Shipment::RETURNED, Shipment::FAILED], true));

            if ($shipments->contains(fn ($s) => $s->status === Shipment::DELIVERED)) {
                $deliveredAt = $shipments->where('status', Shipment::DELIVERED)->map(fn ($s) => $s->delivered_at ?? $s->updated_at)->max();
                if ($deliveredAt && now()->diffInDays($deliveredAt, true) > self::RETURN_DAYS) {
                    throw ValidationException::withMessages(['order' => [__('The :n-day return period for this order has ended.', ['n' => self::RETURN_DAYS])]]);
                }
            }

            $req = \App\Models\OrderRefundRequest::create(['order_id' => $o->id, 'customer_id' => $customer->id, 'reason' => $reason, 'kind' => $left ? 'return' : 'cancel']);
            OrderEvent::record($o, 'refund_requested', $customer, ['reason' => $reason, 'kind' => $req->kind]);
            // The seller hears of it at once: a return may end with their earnings being taken back.
            if ($o->product?->seller_id) {
                User::where('company_id', $o->product->seller_id)->role('seller')->get()->each(fn ($u) => $u->notify(new \App\Notifications\SimpleNotice(
                    $req->kind === 'return' ? 'A customer asked to return order #:id' : 'A customer asked to cancel order #:id', ':why', '/dashboard/seller-orders',
                    ['id' => (string) $o->id, 'why' => mb_substr($reason, 0, 160)],
                )));
            }

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

    /**
     * Approving a return of goods that already left does not refund yet: the parcel has to come back and pass inspection first. The customer gets a
     * code to give whoever collects it. Cancelling before shipping is different: nothing travels, so the refund is immediate.
     */
    public function approve(\App\Models\OrderRefundRequest $req, User $by, ?string $note = null): \App\Models\OrderRefundRequest
    {
        if ($req->kind !== 'return') {
            return $this->refundNow($req, $by, $note);
        }

        return DB::transaction(function () use ($req, $by, $note) {
            $r = \App\Models\OrderRefundRequest::lockForUpdate()->findOrFail($req->id);
            if ($r->status !== 'requested') {
                throw ValidationException::withMessages(['request' => ['This request was already decided.']]);
            }
            $r->update(['status' => 'awaiting_pickup', 'decided_by' => $by->id, 'decided_at' => now(), 'decision_note' => $note, 'return_code' => (string) random_int(100000, 999999)]);
            OrderEvent::record(Order::withoutGlobalScopes()->findOrFail($r->order_id), 'return_approved', $by, []);

            return $r;
        });
    }

    /** The customer picks the day the parcel can be collected. */
    public function schedulePickup(\App\Models\OrderRefundRequest $req, User $customer, string $day, ?string $note): \App\Models\OrderRefundRequest
    {
        if ($req->customer_id !== $customer->id) {
            abort(404);
        }
        if ($req->status !== 'awaiting_pickup') {
            throw ValidationException::withMessages(['request' => ['This return is not waiting for a collection.']]);
        }
        $req->update(['pickup_day' => $day, 'pickup_note' => $note ? mb_substr(trim(strip_tags($note)), 0, 200) : null]);

        return $req;
    }

    /** Whoever collects the parcel types the code the customer gave them: that is the proof it was handed over. */
    public function collect(\App\Models\OrderRefundRequest $req, User $by, string $code): \App\Models\OrderRefundRequest
    {
        if ($req->status !== 'awaiting_pickup') {
            throw ValidationException::withMessages(['request' => ['This return is not waiting for a collection.']]);
        }
        if (! $req->return_code || ! hash_equals($req->return_code, trim($code))) {
            throw ValidationException::withMessages(['code' => ['The return code is wrong.']]);
        }
        $req->update(['status' => 'collected', 'collected_at' => now(), 'collected_by' => $by->id]);
        OrderEvent::record(Order::withoutGlobalScopes()->findOrFail($req->order_id), 'return_collected', $by, []);

        return $req;
    }

    /** The parcel is checked. Passed: the money goes back. Failed: the customer is told why and gets the parcel back, with no refund. */
    public function inspect(\App\Models\OrderRefundRequest $req, User $by, bool $passed, ?string $note): \App\Models\OrderRefundRequest
    {
        if ($req->status !== 'collected') {
            throw ValidationException::withMessages(['request' => ['Only a collected parcel can be inspected.']]);
        }
        if (! $passed && ! filled($note)) {
            throw ValidationException::withMessages(['note' => ['Say what is wrong with the parcel.']]);
        }
        $req->update(['inspection' => $passed ? 'passed' : 'failed', 'inspection_note' => $note ? mb_substr(trim(strip_tags($note)), 0, 300) : null, 'inspected_at' => now(), 'inspected_by' => $by->id]);

        if ($passed) {
            return $this->refundNow($req->fresh(), $by, $note ?: 'The returned parcel passed inspection.', ['collected']);
        }
        $req->update(['status' => 'rejected', 'decision_note' => $req->inspection_note]);
        OrderEvent::record(Order::withoutGlobalScopes()->findOrFail($req->order_id), 'return_failed', $by, ['note' => $req->inspection_note, 'reason' => $req->inspection_note]);

        return $req;
    }

    /** @param list<string> $from the statuses this request may be in */
    private function refundNow(\App\Models\OrderRefundRequest $req, User $by, ?string $note = null, array $from = ['requested']): \App\Models\OrderRefundRequest
    {
        return DB::transaction(function () use ($req, $by, $note, $from) {
            $r = \App\Models\OrderRefundRequest::lockForUpdate()->findOrFail($req->id);
            if (! in_array($r->status, $from, true)) {
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
