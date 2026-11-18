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
    public function index()
    {
        return OrderMessage::where('kind', 'problem')->whereNull('handled_at')->latest('id')->limit(100)->get()->map(function ($m) {
            $order = Order::withoutGlobalScopes()->with('product:id,name')->find($m->order_id);

            return ['id' => $m->id, 'order_id' => $m->order_id, 'product' => $order?->product?->name, 'customer' => User::withoutGlobalScopes()->whereKey($m->user_id)->value('name'), 'body' => $m->body, 'created_at' => $m->created_at?->toIso8601String()];
        })->values();
    }

    public function handled(Request $request, int $id)
    {
        $m = OrderMessage::where('kind', 'problem')->findOrFail($id);
        $m->update(['handled_at' => now(), 'handled_by' => $request->user()->id]);

        return ['id' => $m->id, 'handled' => true];
    }
}
