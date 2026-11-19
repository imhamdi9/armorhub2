<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\OrderGroup;
use App\Services\Cart\CheckoutService;
use App\Services\OrderLifecycleService;
use App\Services\Payment\GroupPayments;
use App\Services\Payment\PaymentUnavailable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Buying a cart: one purchase (an order group), paid once, split into one order per line. */
class CheckoutController extends Controller
{
    public function checkout(Request $request, CheckoutService $checkout)
    {
        $a = $request->validate([
            'shipping_address' => 'required|array',
            'shipping_address.name' => 'required|string|max:120', 'shipping_address.phone' => 'required|string|max:30', 'shipping_address.line1' => 'required|string|max:200',
            'shipping_address.line2' => 'nullable|string|max:200', 'shipping_address.city' => 'required|string|max:100',
            'shipping_address.region' => 'nullable|string|max:100', 'shipping_address.postal_code' => 'nullable|string|max:20',
            'delivery_speed' => 'nullable|in:standard,express', 'preferred_day' => 'nullable|date_format:Y-m-d|after:today|before:'.now()->addDays(15)->toDateString(),
            'gift_note' => 'nullable|string|max:200', 'use_points' => 'nullable|integer|min:0|max:1000000',
        ]);
        $choices = ['delivery_speed' => $a['delivery_speed'] ?? 'standard', 'preferred_day' => $a['preferred_day'] ?? null, 'gift_note' => isset($a['gift_note']) ? trim(strip_tags($a['gift_note'])) : null, 'use_points' => (int) ($a['use_points'] ?? 0)];

        return response()->json($this->present($checkout->checkout($request->user(), $a['shipping_address'], $choices)), 201);
    }

    public function index(Request $request)
    {
        return OrderGroup::where('customer_id', $request->user()->id)->latest('id')->limit(50)->get()->map(fn ($g) => $this->present($g))->values();
    }

    public function show(Request $request, int $id)
    {
        return $this->present($this->mine($request, $id));
    }

    public function pay(Request $request, int $id, GroupPayments $payments)
    {
        $group = $this->mine($request, $id);
        $request->validate(['payment_method' => 'nullable|string|max:40']);

        try {
            $result = $payments->pay($group, $request->input('payment_method'), $request->user());
        } catch (PaymentUnavailable $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        return response()->json($result + ['group' => $this->present($group->fresh())], $result['status'] === 'redirect' ? 202 : 200);
    }

    /** Before paying, the customer may drop the whole purchase. */
    public function cancel(Request $request, int $id, OrderLifecycleService $lifecycle)
    {
        $group = $this->mine($request, $id);
        abort_unless($group->status === OrderGroup::AWAITING, 422, 'Only a purchase that is waiting for payment can be cancelled.');

        DB::transaction(function () use ($group, $lifecycle, $request) {
            foreach ($group->orders()->get() as $order) {
                $lifecycle->cancel($order, $request->user(), 'Purchase cancelled by the customer');
            }
            $group->update(['status' => OrderGroup::CANCELLED]);
            app(\App\Services\Loyalty::class)->release($group);
            // The discount code can be used again: the purchase it was reserved for will not happen.
            DB::table('promo_redemptions')->where('group_id', $group->id)->update(['status' => 'released', 'updated_at' => now()]);
        });

        return $this->present($group->fresh());
    }

    private function mine(Request $request, int $id): OrderGroup
    {
        return OrderGroup::where('customer_id', $request->user()->id)->findOrFail($id);
    }

    /** @return array<string, mixed> */
    private function present(OrderGroup $g): array
    {
        $orders = $g->orders()->with('product:id,name,name_ar,images,seller_id')->get();

        return [
            'id' => $g->id, 'status' => $g->status, 'currency' => $g->currency, 'items_amount' => (float) $g->items_amount, 'service_fee_amount' => (float) $g->service_fee_amount,
            'shipping_amount' => (float) $g->shipping_amount, 'discount_amount' => (float) $g->discount_amount, 'payable_amount' => (float) $g->payable_amount, 'delivery_speed' => $g->delivery_speed, 'preferred_day' => $g->preferred_day?->toDateString(), 'gift_note' => $g->gift_note, 'market' => app(\App\Support\Markets\Markets::class)->forCompany(\App\Models\Company::withoutGlobalScopes()->find($g->company_id))?->code, 'paid_at' => $g->paid_at?->toIso8601String(), 'created_at' => $g->created_at?->toIso8601String(),
            'orders' => $orders->map(fn ($o) => [
                'id' => $o->id, 'product' => $o->product?->name, 'image' => ($o->product?->images ?? [])[0] ?? null, 'quantity' => $o->quantity, 'total_amount' => (float) $o->total_amount,
                'service_fee_amount' => (float) $o->service_fee_amount, 'payable_amount' => (float) $o->payable_amount, 'status' => $o->status instanceof OrderStatus ? $o->status->value : $o->status,
                'statuses' => app(\App\Services\OrderStatuses::class)->forCustomer($o),
            ])->values(),
        ];
    }
}
