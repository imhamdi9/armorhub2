<?php

namespace App\Http\Controllers\Api;

use App\Enums\QuoteStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PartnerQuote;
use App\Services\OrderLifecycleService;
use Illuminate\Http\Request;

class OrderLifecycleController extends Controller
{
    public function __construct(private OrderLifecycleService $lifecycle)
    {
    }

    public function schedule(Request $request, int $id)
    {
        $data = $request->validate(['scheduled_at' => 'required|date|after:now']);
        $order = $this->forPartner($request, $id);

        return $this->respond($this->lifecycle->schedule($order, \Carbon\Carbon::parse($data['scheduled_at']), $request->user()));
    }

    public function start(Request $request, int $id)
    {
        return $this->respond($this->lifecycle->start($this->forPartner($request, $id), $request->user()));
    }

    public function complete(Request $request, int $id)
    {
        return $this->respond($this->lifecycle->complete($this->forPartner($request, $id), $request->user()));
    }

    /** The customer who placed the order (or a platform admin) can cancel before payment. */
    public function cancel(Request $request, int $id)
    {
        $data = $request->validate(['reason' => 'nullable|string|max:500']);
        $order = Order::withoutGlobalScopes()->findOrFail($id);
        $user = $request->user();

        abort_unless($user->hasRole('platform-admin') || $order->customer_id === $user->id, 403);

        return $this->respond($this->lifecycle->cancel($order, $user, $data['reason'] ?? null));
    }

    /** Only staff of the partner whose quote was accepted may work the job. */
    private function forPartner(Request $request, int $id): Order
    {
        $order = Order::withoutGlobalScopes()->findOrFail($id);
        $user = $request->user();

        if ($user->hasRole('platform-admin')) {
            return $order;
        }

        $assigned = PartnerQuote::withoutGlobalScopes()
            ->where('order_id', $order->id)
            ->where('status', QuoteStatus::Accepted)
            ->value('partner_id');

        abort_unless(
            $user->partner_id !== null
                && $user->partner_id === $assigned
                && $user->hasAnyRole(['partner-manager', 'technician']),
            403,
            'This job is not assigned to your partner.'
        );

        return $order;
    }

    private function respond(Order $order)
    {
        return response()->json($order->load(['events.user:id,name', 'warranty']));
    }
}
