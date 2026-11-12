<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InboundShipment;
use App\Models\Product;
use App\Services\Warehouse\WarehouseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** The seller's side of ArmorHub fulfilment: what is stored for it, and the cartons it sends in. */
class SellerFulfilmentController extends Controller
{
    public function __construct(private WarehouseService $stock)
    {
    }

    private function seller(Request $request)
    {
        abort_unless($request->user()->hasRole('seller') && $request->user()->company_id, 403);

        return $request->user()->company()->with('tenant')->first();
    }

    /** The seller's goods and how many units each has in the warehouse. */
    public function stock(Request $request)
    {
        $c = $this->seller($request);
        $products = Product::where('seller_id', $c->id)->where('requires_shipping', true)->orderBy('name')->get(['id', 'name', 'sku', 'fulfilment', 'units_per_carton', 'stock_cartons']);

        return [
            'warehouse' => $this->stock->warehouseFor($c->tenant_id)?->only(['id', 'name', 'city']),
            'products' => $products->map(fn (Product $p) => $p->toArray() + ['units' => $this->stock->unitsOf($p)]),
        ];
    }

    public function index(Request $request)
    {
        $c = $this->seller($request);

        return InboundShipment::where('seller_id', $c->id)->with('items.product:id,name,sku,units_per_carton')->latest('id')->limit(100)->get();
    }

    /** Announce cartons that are on their way to the market's warehouse. */
    public function store(Request $request)
    {
        $c = $this->seller($request);
        $warehouse = $this->stock->warehouseFor($c->tenant_id) ?? throw ValidationException::withMessages(['warehouse' => ['This market has no open warehouse yet.']]);
        $data = $request->validate([
            'items' => 'required|array|min:1|max:50', 'items.*.product_id' => 'required|integer', 'items.*.cartons' => 'required|integer|min:1|max:100000',
            'carrier' => 'nullable|string|max:80', 'tracking_number' => 'nullable|string|max:80', 'note' => 'nullable|string|max:200',
        ]);

        $inbound = DB::transaction(function () use ($data, $c, $warehouse) {
            $inbound = InboundShipment::create(['tenant_id' => $c->tenant_id, 'warehouse_id' => $warehouse->id, 'seller_id' => $c->id]
                + collect($data)->only(['carrier', 'tracking_number', 'note'])->all());
            foreach ($data['items'] as $row) {
                $product = Product::where('seller_id', $c->id)->where('fulfilment', 'armorhub')->find($row['product_id'])
                    ?? throw ValidationException::withMessages(['items' => ['Only products you chose to have ArmorHub store can be sent in.']]);
                $inbound->items()->create(['product_id' => $product->id, 'cartons_expected' => $row['cartons']]);
            }

            return $inbound;
        });

        return response()->json($inbound->load('items.product:id,name,sku,units_per_carton'), 201);
    }

    public function cancel(Request $request, int $id)
    {
        $inbound = InboundShipment::where('seller_id', $this->seller($request)->id)->findOrFail($id);
        if ($inbound->status !== 'announced') {
            throw ValidationException::withMessages(['status' => ['Only a delivery that has not arrived can be cancelled.']]);
        }
        $inbound->update(['status' => 'cancelled']);

        return $inbound;
    }
}
