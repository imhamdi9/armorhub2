<?php

namespace App\Services\Warehouse;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Stock of the goods ArmorHub stores and ships for sellers. Units only move through a recorded movement. */
class WarehouseService
{
    /** The warehouse a market's goods are kept in (the first open one). */
    public function warehouseFor(int $tenantId): ?Warehouse
    {
        return Warehouse::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('id')->first();
    }

    public function unitsOf(Product $product): int
    {
        return (int) DB::table('warehouse_stock')->where('product_id', $product->id)->sum('units');
    }

    /**
     * Units promised to buyers but still on the shelf: orders waiting for payment (held for an hour) and paid orders the warehouse has not shipped.
     * Without this two buyers could both pay for the last unit.
     */
    public function reservedOf(Product $product): int
    {
        return (int) \App\Models\Order::withoutGlobalScopes()->where('product_id', $product->id)->where('fulfilment', 'armorhub')
            ->where(fn ($q) => $q->where('status', 'paid')->orWhere(fn ($w) => $w->where('status', 'awaiting_payment')->where('created_at', '>=', now()->subHour())))
            ->whereNull('refund_status')->whereDoesntHave('shipments', fn ($s) => $s->whereNotIn('status', ['cancelled']))->sum('quantity');
    }

    /** What a buyer can still get: what the warehouse holds less what is already promised. */
    public function availableOf(Product $product): int
    {
        return max(0, $this->unitsOf($product) - $this->reservedOf($product));
    }

    /** A product kept by ArmorHub can only be bought while the warehouse has units that nobody else has been promised. */
    public function assertInStock(Product $product, int $quantity = 1): void
    {
        if ($product->fulfilment === 'armorhub' && $this->availableOf($product) < max(1, $quantity)) {
            throw ValidationException::withMessages(['product_id' => ['This product is out of stock.']]);
        }
    }

    public function move(int $warehouseId, int $productId, int $units, string $type, ?User $by = null, ?string $refType = null, ?int $refId = null, ?string $note = null): void
    {
        $restocked = false;
        DB::transaction(function () use ($warehouseId, $productId, $units, $type, $by, $refType, $refId, $note, &$restocked) {
            $before = (int) DB::table('warehouse_stock')->where('product_id', $productId)->sum('units');
            DB::table('warehouse_stock')->insertOrIgnore(['warehouse_id' => $warehouseId, 'product_id' => $productId, 'units' => 0]);
            $row = DB::table('warehouse_stock')->where(['warehouse_id' => $warehouseId, 'product_id' => $productId])->lockForUpdate()->first();

            if ($row->units + $units < 0) {
                throw ValidationException::withMessages(['units' => ['The warehouse does not hold that many units.']]);
            }
            DB::table('warehouse_stock')->where('id', $row->id)->update(['units' => $row->units + $units]);
            DB::table('stock_movements')->insert([
                'warehouse_id' => $warehouseId, 'product_id' => $productId, 'units' => $units, 'type' => $type, 'reference_type' => $refType,
                'reference_id' => $refId, 'user_id' => $by?->id, 'note' => $note, 'created_at' => now(), 'updated_at' => now(),
            ]);
            // The product page and the seller's list read one number.
            $total = (int) DB::table('warehouse_stock')->where('product_id', $productId)->sum('units');
            $upc = (int) (Product::withoutGlobalScopes()->whereKey($productId)->value('units_per_carton') ?: 1);
            Product::withoutGlobalScopes()->whereKey($productId)->update(['stock_cartons' => intdiv($total, $upc)]);
            $restocked = $before <= 0 && $total > 0;
        });

        // Customers who saved this product while it was out of stock are told it is back.
        if ($restocked) {
            $name = (string) Product::withoutGlobalScopes()->whereKey($productId)->value('name');
            foreach (DB::table('favourites')->where('product_id', $productId)->pluck('user_id') as $userId) {
                app(\App\Services\InstallationNotifier::class)->user((int) $userId, 'Back in stock', ':name is available again.', '/dashboard/favourites', ['name' => (string) $name]);
            }
        }
    }

    /** Take the order's unit out of the warehouse when it leaves. */
    public function ship(Order $order, ?User $by): void
    {
        $warehouseId = $order->warehouse_id ?? $this->warehouseFor((int) \App\Models\Company::withoutGlobalScopes()->whereKey($order->company_id)->value('tenant_id'))?->id;
        abort_unless($warehouseId, 422, 'This market has no open warehouse.');
        $this->move($warehouseId, $order->product_id, -max(1, (int) $order->quantity), 'shipped', $by, 'order', $order->id);
    }
}
