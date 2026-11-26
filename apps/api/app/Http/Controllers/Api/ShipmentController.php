<?php

namespace App\Http\Controllers\Api;

use App\Enums\QuoteStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PartnerQuote;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Shipping\ShipmentService;
use App\Services\Shipping\ShippingProviders;
use App\Services\Shipping\ShippingUnavailable;
use App\Services\Shipping\TrackingEvent;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShipmentController extends Controller
{
    public function __construct(private ShipmentService $shipments, private ShippingProviders $providers)
    {
    }

    /** What staff may choose from when booking: the providers that are ready to use. */
    public function providers(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(['platform-admin', 'company-owner', 'partner-manager', 'technician', 'seller']), 403);

        return [
            'default' => $this->providers->activeKey(),
            'providers' => collect($this->providers->all())->filter(fn ($p) => $this->providers->isConfigured($p->key()))
                ->map(fn ($p) => ['key' => $p->key(), 'label' => $p->label(), 'manual' => $p->key() === 'manual'])->values(),
        ];
    }

    public function index(Request $request, int $id)
    {
        $order = Order::withoutGlobalScopes()->with('product:id,name,name_ar,requires_shipping,seller_id')->findOrFail($id);
        $role = $this->role($request->user(), $order) ?? abort(404);

        return [
            'requires_shipping' => (bool) $order->product?->requires_shipping,
            'can_manage' => $role === 'staff',
            'address' => $order->makeVisible('shipping_address')->shipping_address,
            'delivery_speed' => $order->delivery_speed, 'preferred_day' => $order->preferred_day?->toDateString(), 'gift_note' => $order->gift_note,
            'shipments' => Shipment::where('order_id', $order->id)->with('events')->latest('id')->get()
                // The code is for the person who receives the parcel, while it is still on its way.
                ->each(fn (Shipment $s) => $role === 'customer' && ! $s->isFinal() && $s->status !== Shipment::FAILED ? $s->makeVisible('delivery_code') : null),
        ];
    }

    public function rates(Request $request, int $id)
    {
        $order = $this->managedOrder($request, $id);
        $key = $request->validate(['provider' => 'nullable|string'])['provider'] ?? $this->providers->activeKey();

        try {
            return ['rates' => $this->shipments->quote($order, $key)];
        } catch (ShippingUnavailable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function store(Request $request, int $id)
    {
        $order = $this->managedOrder($request, $id);
        $data = $request->validate([
            'provider' => 'nullable|string|max:40',
            'service' => 'nullable|string|max:60',
            'carrier' => 'nullable|string|max:80',
            'tracking_number' => 'nullable|string|max:80',
            'cost' => 'nullable|numeric|min:0|max:1000000',
        ]);
        $key = $data['provider'] ?? $this->providers->activeKey();

        try {
            $shipment = $this->shipments->create($order, $request->user(), $key, $data);
        } catch (ShippingUnavailable $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['provider' => [$e->getMessage()]]], 422);
        }

        Audit::record('shipment.created', $shipment, ['order_id' => $order->id, 'provider' => $key]);

        return response()->json($shipment, 201);
    }

    /** Ask the carrier for the latest tracking. */
    public function refresh(Request $request, int $id)
    {
        $shipment = Shipment::findOrFail($id);
        $order = $this->managedOrder($request, $shipment->order_id);

        try {
            $new = $this->shipments->refresh($shipment);
        } catch (ShippingUnavailable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return ['updates' => $new, 'shipment' => $shipment->fresh('events'), 'order_id' => $order->id];
    }

    /** Staff move a shipment along by hand (the only way for manual shipments). */
    public function status(Request $request, int $id)
    {
        $shipment = Shipment::findOrFail($id);
        $this->managedOrder($request, $shipment->order_id);
        $data = $request->validate(['status' => ['required', Rule::in(Shipment::STATUSES)], 'description' => 'nullable|string|max:250', 'location' => 'nullable|string|max:120', 'code' => 'nullable|string|max:8']);

        // Delivered means the customer gave the courier the code (an administrator may close it without one, and it is written down).
        if ($data['status'] === Shipment::DELIVERED && $shipment->delivery_code && ! $shipment->isFinal()) {
            if (hash_equals($shipment->delivery_code, trim((string) ($data['code'] ?? '')))) {
                $data['description'] = $data['description'] ?? 'Delivered: the customer gave the delivery code';
            } elseif ($request->user()->hasRole('platform-admin')) {
                $data['description'] = ($data['description'] ?? 'Delivered').' (closed by an administrator without the code)';
            } else {
                return response()->json(['message' => 'The delivery code is wrong.', 'errors' => ['code' => ['The delivery code is wrong.']]], 422);
            }
        }

        $this->shipments->apply($shipment, new TrackingEvent($data['status'], $shipment->tracking_number, $shipment->reference, $data['description'] ?? null, $data['location'] ?? null, now()), $request->user());
        Audit::record('shipment.status_set', $shipment, ['status' => $data['status']]);

        return $shipment->fresh('events');
    }

    /** The customer chooses another day after a failed attempt. */
    public function reschedule(Request $request, int $id)
    {
        $shipment = Shipment::findOrFail($id);
        $order = Order::withoutGlobalScopes()->findOrFail($shipment->order_id);
        abort_unless($this->role($request->user(), $order) === 'customer', 404);
        $data = $request->validate(['date' => 'required|date_format:Y-m-d|after:today|before:'.now()->addDays(15)->toDateString(), 'note' => 'nullable|string|max:200']);

        return $this->shipments->reschedule($shipment, $data['date'], $data['note'] ?? null, $request->user());
    }

    /** @return 'staff'|'customer'|null */
    private function role(User $user, Order $order): ?string
    {
        if ($user->hasRole('platform-admin')) {
            return 'staff';
        }
        if ($user->hasRole('customer')) {
            return $order->customer_id === $user->id ? 'customer' : null;
        }
        if ($user->hasRole('company-owner') && $user->company_id === $order->company_id) {
            return 'staff';
        }
        // The seller of the product ships it.
        if ($user->hasRole('seller') && $order->fulfilment !== 'armorhub' && $order->product?->seller_id !== null && $order->product->seller_id === $user->company_id) {
            return 'staff';
        }
        // Goods ArmorHub keeps are shipped by the warehouse team of that market.
        if ($order->fulfilment === 'armorhub' && $user->can('warehouse.manage')
            && (int) $user->company?->tenant_id === (int) \App\Models\Company::withoutGlobalScopes()->whereKey($order->company_id)->value('tenant_id')) {
            return 'staff';
        }
        if ($user->hasAnyRole(['partner-manager', 'technician']) && $user->partner_id
            && PartnerQuote::withoutGlobalScopes()->where('order_id', $order->id)->where('partner_id', $user->partner_id)->where('status', QuoteStatus::Accepted)->exists()) {
            return 'staff';
        }

        return null;
    }

    private function managedOrder(Request $request, int $orderId): Order
    {
        $order = Order::withoutGlobalScopes()->with('product')->findOrFail($orderId);

        return $this->role($request->user(), $order) === 'staff' ? $order : abort(404);
    }
}
