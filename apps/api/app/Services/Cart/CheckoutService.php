<?php

namespace App\Services\Cart;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\OrderGroup;
use App\Models\User;
use App\Services\Commission\CustomerFee;
use App\Services\SubscriptionService;
use App\Services\Warehouse\WarehouseService;
use App\Support\Markets\Markets;
use App\Support\Settings\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns a cart into one purchase the customer pays once. Each line becomes its own order (its seller, its shipment and its settlement stay
 * separate), and all of them hang from one group that holds the total.
 */
class CheckoutService
{
    public function __construct(private CartService $carts, private Markets $markets, private CustomerFee $fees, private SubscriptionService $plans, private WarehouseService $stock, private Settings $settings)
    {
    }

    /** @param array{name: string, phone: string, line1: string, line2?: ?string, city: string, region?: ?string, postal_code?: ?string} $address */
    public function checkout(User $user, array $address): OrderGroup
    {
        if (! $this->settings->bool('platform.orders_enabled')) {
            throw ValidationException::withMessages(['cart' => ['New orders are paused for now. Please try again later.']]);
        }

        $cart = $this->carts->cartOf($user);
        $items = $cart->items()->get();
        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['cart' => ['Your cart is empty.']]);
        }

        $company = $user->company;
        $phone = $this->markets->phoneFor($company, $address['phone']) ?? throw ValidationException::withMessages(['shipping_address.phone' => ['Please enter a valid mobile number for delivery.']]);
        $country = $this->markets->forCompany($company)?->code ?? 'EG';
        $ship = ['name' => $address['name'], 'phone' => $phone, 'line1' => $address['line1'], 'line2' => $address['line2'] ?? null, 'city' => $address['city'],
            'region' => $address['region'] ?? null, 'postal_code' => $address['postal_code'] ?? null, 'country' => $country];

        // Every line is checked again, now: prices, stock and availability may have changed since it was put in the cart.
        $lines = [];
        $sellers = [];
        foreach ($items as $item) {
            $line = $this->carts->resolve($user, $item->product_id, $item->target_id, $item->quantity, $item->vehicle_id);
            $this->stock->assertInStock($line['product'], $item->quantity);
            $lines[] = $line + ['quantity' => $item->quantity];
            $sellers[(int) $line['product']->seller_id] = true;
        }
        foreach (array_keys($sellers) as $sellerId) {
            $this->plans->assertCanTakeCustomers('seller', $sellerId);
        }

        return DB::transaction(function () use ($user, $company, $lines, $ship, $cart) {
            $group = OrderGroup::create([
                'company_id' => $user->company_id, 'customer_id' => $user->id, 'currency' => $lines[0]['price']['currency'], 'items_amount' => 0, 'service_fee_amount' => 0,
                'payable_amount' => 0, 'status' => OrderGroup::AWAITING, 'shipping_address' => $ship,
            ]);

            $items = $fee = 0.0;
            foreach ($lines as $line) {
                $total = round((float) $line['price']['total'] * $line['quantity'], 2);
                $order = Order::create([
                    'company_id' => $user->company_id, 'customer_id' => $user->id, 'group_id' => $group->id, 'product_id' => $line['product']->id, 'quantity' => $line['quantity'],
                    'subject_type' => $line['vehicle'] ? 'vehicle_model' : null, 'subject_id' => $line['vehicle']?->vehicle_model_id,
                    'status' => OrderStatus::AwaitingPayment, 'product_amount' => $total, 'service_amount' => 0, 'commission_amount' => 0, 'total_amount' => $total,
                    'currency' => $line['price']['currency'], 'shipping_address' => $ship,
                ] + $this->markets->taxFor($company, $total) + $this->fees->forInvoice($total));
                OrderEvent::record($order, 'created', $user, ['direct_sale' => true, 'group_id' => $group->id, 'quantity' => $line['quantity']] + ($line['vehicle'] ? ['vehicle' => $line['vehicle']->label()] : []));
                $items += (float) $order->total_amount;
                $fee += (float) $order->service_fee_amount;
            }

            $group->update(['items_amount' => round($items, 2), 'service_fee_amount' => round($fee, 2), 'payable_amount' => round($items + $fee, 2)]);
            $this->carts->clear($user);

            return $group->fresh();
        });
    }
}
