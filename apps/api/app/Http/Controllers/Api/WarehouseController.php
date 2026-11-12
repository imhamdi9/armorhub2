<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InboundShipment;
use App\Models\Order;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\Shipping\ShipmentService;
use App\Services\Warehouse\WarehouseService;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** A market's warehouses and the people who run them: receiving, stock, and packing orders. Everything is limited to one market. */
class WarehouseController extends Controller
{
    public function __construct(private WarehouseService $stock)
    {
    }

    /** The market this person works in; platform admins say which with ?market=ID. */
    private function tenant(Request $request): int
    {
        $user = $request->user();
        if ($user->hasRole('platform-admin')) {
            return (int) $request->validate(['market' => 'required|integer|exists:tenants,id'])['market'];
        }

        return (int) ($user->company?->tenant_id ?? abort(403));
    }

    public function index(Request $request)
    {
        return Warehouse::where('tenant_id', $this->tenant($request))->orderBy('id')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:120', 'city' => 'nullable|string|max:120', 'address' => 'nullable|string|max:250']);
        $w = Warehouse::create($data + ['tenant_id' => $this->tenant($request)]);
        Audit::record('warehouse.created', $w, ['name' => $w->name], $request->user());

        return response()->json($w, 201);
    }

    public function update(Request $request, int $id)
    {
        $w = Warehouse::where('tenant_id', $this->tenant($request))->findOrFail($id);
        $w->update($request->validate(['name' => 'sometimes|required|string|max:120', 'city' => 'nullable|string|max:120', 'address' => 'nullable|string|max:250', 'is_active' => 'sometimes|boolean']));

        return $w;
    }

    /** What the warehouse holds, product by product. */
    public function stockList(Request $request)
    {
        $tenant = $this->tenant($request);

        return DB::table('warehouse_stock as s')->join('warehouses as w', 'w.id', '=', 's.warehouse_id')->join('products as p', 'p.id', '=', 's.product_id')
            ->where('w.tenant_id', $tenant)->orderBy('p.name')
            ->get(['s.warehouse_id', 'w.name as warehouse', 'p.id as product_id', 'p.name', 'p.sku', 'p.units_per_carton', 's.units']);
    }

    /** A counted correction (+ or -), always with a reason. */
    public function adjust(Request $request)
    {
        $data = $request->validate(['warehouse_id' => 'required|integer', 'product_id' => 'required|integer', 'units' => 'required|integer|not_in:0|min:-100000|max:100000', 'note' => 'required|string|max:200']);
        $w = Warehouse::where('tenant_id', $this->tenant($request))->findOrFail($data['warehouse_id']);
        $this->ownProduct($request, $data['product_id']);
        $this->stock->move($w->id, $data['product_id'], $data['units'], 'adjustment', $request->user(), null, null, $data['note']);

        return ['units' => DB::table('warehouse_stock')->where(['warehouse_id' => $w->id, 'product_id' => $data['product_id']])->value('units')];
    }

    public function inbound(Request $request)
    {
        return InboundShipment::where('tenant_id', $this->tenant($request))->with('items.product:id,name,sku,units_per_carton')->latest('id')->limit(100)->get();
    }

    /** The cartons that arrived (what was counted, which may differ from what the seller announced). */
    public function receive(Request $request, int $id)
    {
        $inbound = InboundShipment::where('tenant_id', $this->tenant($request))->with('items')->findOrFail($id);
        if ($inbound->status !== 'announced') {
            throw ValidationException::withMessages(['status' => ['This delivery was already handled.']]);
        }
        $data = $request->validate(['items' => 'required|array|min:1', 'items.*.id' => 'required|integer', 'items.*.cartons_received' => 'required|integer|min:0|max:1000000']);

        DB::transaction(function () use ($inbound, $data, $request) {
            foreach ($data['items'] as $row) {
                $item = $inbound->items->firstWhere('id', $row['id']) ?? throw ValidationException::withMessages(['items' => ['Unknown line.']]);
                $units = $row['cartons_received'] * (int) ($item->product->units_per_carton ?: 1);
                $item->update(['cartons_received' => $row['cartons_received']]);
                if ($units > 0) {
                    $this->stock->move($inbound->warehouse_id, $item->product_id, $units, 'received', $request->user(), 'inbound', $inbound->id);
                }
            }
            $inbound->update(['status' => 'received', 'received_by' => $request->user()->id, 'received_at' => now()]);
        });
        Audit::record('warehouse.received', $inbound, [], $request->user());

        return $inbound->fresh('items.product:id,name,sku,units_per_carton');
    }

    /** Orders for goods ArmorHub keeps, paid and waiting to be packed and sent. */
    public function orders(Request $request)
    {
        $tenant = $this->tenant($request);

        return Order::withoutGlobalScopes()->where('fulfilment', 'armorhub')->whereIn('status', ['paid'])
            ->whereIn('company_id', \App\Models\Company::withoutGlobalScopes()->where('tenant_id', $tenant)->select('id'))
            ->whereDoesntHave('shipments')
            ->with('product:id,name,sku,units_per_carton')->oldest('id')->get()
            ->map(fn (Order $o) => $o->makeVisible('shipping_address'));
    }

    /** Pack and send: creates the shipment with the market's carrier setting and takes the unit out of stock. */
    public function ship(Request $request, int $id, ShipmentService $shipments, \App\Services\Shipping\ShippingProviders $providers)
    {
        $tenant = $this->tenant($request);
        $order = Order::withoutGlobalScopes()->where('fulfilment', 'armorhub')
            ->whereIn('company_id', \App\Models\Company::withoutGlobalScopes()->where('tenant_id', $tenant)->select('id'))->findOrFail($id);
        $data = $request->validate(['carrier' => 'nullable|string|max:80', 'tracking_number' => 'nullable|string|max:80']);

        $shipment = DB::transaction(function () use ($order, $request, $shipments, $providers, $data) {
            $shipments->assertShippable($order);
            $this->stock->ship($order, $request->user());

            return $shipments->create($order, $request->user(), $providers->activeKey(), $data);
        });
        Audit::record('warehouse.shipped', $order, ['shipment' => $shipment->id], $request->user());

        return response()->json($shipment, 201);
    }

    private function ownProduct(Request $request, int $productId): Product
    {
        $tenant = $this->tenant($request);

        return Product::withoutGlobalScopes()->where('fulfilment', 'armorhub')
            ->whereIn('seller_id', \App\Models\Company::withoutGlobalScopes()->where('tenant_id', $tenant)->select('id'))->findOrFail($productId);
    }
}
