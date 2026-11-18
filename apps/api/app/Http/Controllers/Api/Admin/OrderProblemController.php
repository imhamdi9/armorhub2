<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderMessage;
use App\Models\User;
use Illuminate\Http\Request;

/** Problems customers flagged on their orders, waiting for ArmorHub. */
class OrderProblemController extends Controller
{
    public function index(Request $request)
    {
        $ids = \App\Support\MarketScope::companyIds($request->user());

        return OrderMessage::where('kind', 'problem')->whereNull('handled_at')->when($ids !== null, fn ($q) => $q->whereIn('order_id', Order::withoutGlobalScopes()->whereIn('company_id', $ids)->select('id')))->latest('id')->limit(100)->get()->map(function ($m) {
            $order = Order::withoutGlobalScopes()->with('product:id,name,name_ar')->find($m->order_id);

            return ['id' => $m->id, 'order_id' => $m->order_id, 'product' => $order?->product?->name, 'customer' => User::withoutGlobalScopes()->whereKey($m->user_id)->value('name'), 'body' => $m->body, 'created_at' => $m->created_at?->toIso8601String()];
        })->values();
    }

    public function handled(Request $request, int $id)
    {
        $ids = \App\Support\MarketScope::companyIds($request->user());
        $m = OrderMessage::where('kind', 'problem')->when($ids !== null, fn ($q) => $q->whereIn('order_id', Order::withoutGlobalScopes()->whereIn('company_id', $ids)->select('id')))->findOrFail($id);
        $request->validate(['note' => 'nullable|string|max:500']);
        $note = trim(strip_tags((string) $request->input('note', '')));
        $m->update(['handled_at' => now(), 'handled_by' => $request->user()->id]);
        // The customer who raised it hears that it was looked at, and what was decided.
        $order = Order::withoutGlobalScopes()->find($m->order_id);
        if ($m->user_id === null && $order?->guest_email) {
            \Illuminate\Support\Facades\Notification::route('mail', $order->guest_email)->notify(new \App\Notifications\GuestOrderUpdate("Your problem with order #{$order->id} was looked at", $note !== '' ? $note : 'We reviewed it. Write to us from the order if you need more.', $order->guest_token, $order->guest_locale));
        }
        User::withoutGlobalScopes()->find($m->user_id)?->notify(new \App\Notifications\SimpleNotice('Your problem with order #:id was looked at', $note !== '' ? $note : 'We reviewed it. Write to us from the order if you need more.', "/dashboard/orders/{$m->order_id}", ['id' => (string) $m->order_id]));

        return ['id' => $m->id, 'handled' => true];
    }
}
