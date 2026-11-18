<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderRefundRequest;
use App\Services\OrderRefundRequests;
use Illuminate\Http\Request;

/** A customer asking to cancel a paid order or return it, and what became of the request. */
class RefundRequestController extends Controller
{
    public function store(Request $request, int $id, OrderRefundRequests $requests)
    {
        $data = $request->validate(['reason' => 'required|string|min:3|max:300', 'photos' => 'nullable|array|max:4', 'photos.*' => ['string', 'regex:#^/media/[0-9a-f-]{36}\.(jpg|png|webp|gif)$#']]);
        $order = Order::withoutGlobalScopes()->where('customer_id', $request->user()->id)->findOrFail($id);

        // Only pictures this person uploaded themselves can be attached.
        $photos = array_values(array_unique($data['photos'] ?? []));
        if (\App\Models\MediaFile::whereIn('name', array_map(fn ($p) => substr($p, 7), $photos))->where('uploaded_by', $request->user()->id)->count() !== count($photos)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['photos' => ['Only photos you uploaded can be added.']]);
        }
        $result = $requests->request($order, $request->user(), $data['reason']);
        if ($photos) {
            OrderRefundRequest::whereKey($result['id'])->update(['photos' => json_encode($photos)]);
        }

        return response()->json($result, 201);
    }

    /** One picture of the problem (the box, the damage, the wrong item). Only the buyer of the order may add one. */
    public function photo(Request $request, int $id, \App\Services\MediaLibrary $media)
    {
        Order::withoutGlobalScopes()->where('customer_id', $request->user()->id)->findOrFail($id);
        $request->validate(['image' => 'required|file|max:'.\App\Services\MediaLibrary::MAX_KB]);
        try {
            $file = $media->store($request->file('image'), $request->user()->id);
        } catch (\InvalidArgumentException $e) {
            throw \Illuminate\Validation\ValidationException::withMessages(['image' => [$e->getMessage()]]);
        }

        return response()->json(['path' => "/media/{$file->name}"], 201);
    }

    /** The customer chooses the day the parcel can be collected. */
    public function pickup(Request $request, int $id, OrderRefundRequests $requests)
    {
        $data = $request->validate(['day' => 'required|date_format:Y-m-d|after:today|before:'.now()->addDays(8)->toDateString(), 'note' => 'nullable|string|max:200']);
        $req = OrderRefundRequest::where('order_id', $id)->where('customer_id', $request->user()->id)->latest('id')->firstOrFail();
        $r = $requests->schedulePickup($req, $request->user(), $data['day'], $data['note'] ?? null);

        return ['status' => $r->status, 'pickup_day' => $r->pickup_day?->toDateString()];
    }

    /** The newest request for an order, and whether a new one may be made, so the screen shows the right thing. */
    public function show(Request $request, int $id)
    {
        $order = Order::withoutGlobalScopes()->with('product:id,requires_shipping')->where('customer_id', $request->user()->id)->findOrFail($id);
        $last = OrderRefundRequest::where('order_id', $order->id)->latest('id')->first();

        return [
            'request' => $last ? ['id' => $last->id, 'kind' => $last->kind, 'status' => $last->status, 'reason' => $last->reason, 'note' => $last->decision_note, 'created_at' => $last->created_at?->toIso8601String(),
                // The code is the customer's to give whoever collects the parcel.
                'return_code' => $last->status === 'awaiting_pickup' ? $last->return_code : null, 'pickup_day' => $last->pickup_day?->toDateString(), 'inspection' => $last->inspection, 'inspection_note' => $last->inspection_note, 'photos' => $last->photos ?? []] : null,
            'refund_status' => $order->refund_status, 'refund_amount' => $order->refund_amount !== null ? (float) $order->refund_amount : null,
            'can_request' => (bool) $order->product?->requires_shipping && ! $order->refund_status && ($last === null || ! in_array($last->status, ['requested', 'awaiting_pickup', 'collected'], true))
                && in_array($order->status->value ?? $order->status, ['paid', 'appointment_scheduled', 'service_in_progress', 'completed'], true),
            'return_days' => OrderRefundRequests::RETURN_DAYS,
        ];
    }
}
