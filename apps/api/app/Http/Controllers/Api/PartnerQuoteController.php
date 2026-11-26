<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PartnerQuote;
use App\Services\QuoteService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class PartnerQuoteController extends Controller
{
    use AuthorizesRequests;

    /** Quotes for an order the user is allowed to see. */
    public function index(Order $order)
    {
        $this->authorize('view', $order);

        return PartnerQuote::withoutGlobalScopes()
            ->where('order_id', $order->id)
            ->with(['partner' => fn ($q) => $q->withoutGlobalScopes()])
            ->get();
    }

    /** Only the customer who placed the order (or a platform admin) picks a quote. */
    public function accept(int $id, QuoteService $quotes)
    {
        $quote = PartnerQuote::withoutGlobalScopes()->findOrFail($id);
        $order = Order::withoutGlobalScopes()->findOrFail($quote->order_id);
        $user = auth()->user();

        abort_unless(
            $user->hasRole('platform-admin') || $order->customer_id === $user->id,
            403
        );

        return response()->json([
            'message' => 'Partner quote accepted',
            'order' => $quotes->accept($quote, $user),
        ]);
    }
}
