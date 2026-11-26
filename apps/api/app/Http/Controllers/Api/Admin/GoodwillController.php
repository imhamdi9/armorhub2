<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Services\Loyalty;
use App\Support\MarketScope;
use Illuminate\Http\Request;

/** Support gives a customer points when something went wrong and a refund is not the answer. Limited to the agent's own market. */
class GoodwillController extends Controller
{
    public function store(Request $request, int $id, Loyalty $loyalty)
    {
        $data = $request->validate(['points' => 'required|integer|min:1|max:100000', 'why' => 'required|string|min:3|max:300']);
        $ids = MarketScope::companyIds($request->user());
        $order = Order::withoutGlobalScopes()->when($ids !== null, fn ($q) => $q->whereIn('company_id', $ids))->findOrFail($id);

        $given = $loyalty->goodwill($order, (int) $data['points'], trim(strip_tags($data['why'])), $request->user());
        User::withoutGlobalScopes()->find($order->customer_id)?->notify(new \App\Notifications\SimpleNotice(
            'We added :points points to your account', 'About order #:id: :why', "/dashboard/orders/{$order->id}",
            ['points' => (string) $given, 'id' => (string) $order->id, 'why' => trim(strip_tags($data['why']))],
        ));

        return ['order_id' => $order->id, 'points' => $given, 'balance' => app(\App\Services\Reputation::class)->points((int) $order->customer_id)];
    }
}
