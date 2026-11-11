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
        $order = Order::withoutGlobalScopes()->with('product:id,name,requires_shipping,seller_id')->findOrFail($id);
        $role = $this->role($request->user(), $order) ?? abort(404);

        return [
            'requires_shipping' => (bool) $order->product?->requires_shipping,
            'can_manage' => $role === 'staff',
            'address' => $order->makeVisible('shipping_address')->shipping_address,
            'shipments' => Shipment::where('order_id', $order->id)->with('events')->latest('id')->get(),
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
        $data = $request->validate(['status' => ['required', Rule::in(Shipment::STATUSES)], 'description' => 'nullable|string|max:250', 'location' => 'nullable|string|max:120']);

        $this->shipments->apply($shipment, new TrackingEvent($data['status'], $shipment->tracking_number, $shipment->reference, $data['description'] ?? null, $data['location'] ?? null, now()), $request->user());
        Audit::record('shipment.status_set', $shipment, ['status' => $data['status']]);

        return $shipment->fresh('events');
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
        if ($user->hasRole('seller') && $order->product?->seller_id !== null && $order->product->seller_id === $user->company_id) {
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
