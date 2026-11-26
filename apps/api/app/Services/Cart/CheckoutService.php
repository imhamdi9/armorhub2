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
    public function checkout(User $user, array $address, array $choices = []): OrderGroup
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

        // Faster delivery only where the market offers it, and only for goods that are shipped.
        $speed = ($choices['delivery_speed'] ?? 'standard') === 'express' ? 'express' : 'standard';
        $express = $speed === 'express' ? app(\App\Services\ShippingFees::class)->express($this->markets->forCompany($company)?->code) : null;
        if ($speed === 'express' && (! $express || collect($lines)->contains(fn ($l) => ! $l['product']->requires_shipping))) {
            throw ValidationException::withMessages(['delivery_speed' => ['Express delivery is not available for this purchase.']]);
        }
        $extras = ['delivery_speed' => $speed, 'preferred_day' => $choices['preferred_day'] ?? null, 'gift_note' => ($choices['gift_note'] ?? null) ?: null];

        return DB::transaction(function () use ($user, $company, $lines, $ship, $cart, $express, $extras, $choices) {
            // One cart becomes one purchase: a second request arriving at the same moment waits here, then finds the cart already bought.
            \App\Models\Cart::whereKey($cart->id)->lockForUpdate()->first();
            if ($cart->items()->count() !== count($lines)) {
                throw ValidationException::withMessages(['cart' => ['Your cart is empty.']]);
            }
            // The last unit can only be sold once: lock the products and look again, now that nobody else can be taking them at the same moment.
            \App\Models\Product::withoutGlobalScopes()->whereIn('id', collect($lines)->pluck('product.id'))->orderBy('id')->lockForUpdate()->get();
            foreach ($lines as $l) {
                $this->stock->assertInStock($l['product'], $l['quantity']);
            }

            $group = OrderGroup::create([
                'company_id' => $user->company_id, 'customer_id' => $user->id, 'currency' => $lines[0]['price']['currency'], 'items_amount' => 0, 'service_fee_amount' => 0,
                'payable_amount' => 0, 'status' => OrderGroup::AWAITING, 'shipping_address' => $ship,
            ] + $extras);

            // Delivery is charged once per seller, on that seller's first order, and goes to that seller.
            $market = $this->markets->forCompany($company)?->code;
            $perSeller = [];
            foreach ($lines as $l) {
                $perSeller[(int) $l['product']->seller_id] = ($perSeller[(int) $l['product']->seller_id] ?? 0.0) + round((float) $l['price']['total'] * $l['quantity'], 2);
            }
            $perProduct = [];
            foreach ($lines as $l) {
                $perProduct[(int) $l['product']->id] = ($perProduct[(int) $l['product']->id] ?? 0.0) + round((float) $l['price']['total'] * $l['quantity'], 2);
            }
            $charged = [];
            $shippingTotal = 0.0;

            // A discount code is checked again now and shared across the orders in proportion to what each costs.
            $promo = null;
            $discount = 0.0;
            if ($cart->promo_code) {
                $checked = app(\App\Services\PromoCodes::class)->check($cart->promo_code, $user, round(array_sum($perSeller), 2), $market, $perProduct);
                $promo = $checked['code'];
                $discount = $checked['discount'];
            }
            $promoDiscount = $discount;

            // Points the customer chose to spend are a discount too, capped at a share of the purchase.
            $loyalty = app(\App\Services\Loyalty::class);
            $pointsUsed = max(0, (int) ($choices['use_points'] ?? 0));
            if ($pointsUsed > 0) {
                if ($pointsUsed > $loyalty->redeemable($user, round(array_sum($perSeller), 2))['max_use']) {
                    throw ValidationException::withMessages(['use_points' => ['You cannot use that many points on this purchase.']]);
                }
                $discount += $loyalty->worth($pointsUsed);
            }
            $lineTotals = array_map(fn ($l) => round((float) $l['price']['total'] * $l['quantity'], 2), $lines);
            $codes = app(\App\Services\PromoCodes::class);
            $ownerId = (int) ($promo?->company_id ?? 0);
            // A seller's coupon is shared over the lines of the products the seller chose for it; every other discount (points, ArmorHub's codes) over all of them.
            $promoShares = array_fill(0, count($lines), 0.0);
            if ($ownerId) {
                $chosen = array_map('intval', (array) ($promo->product_ids ?? []));
                $mine = array_keys(array_filter($lines, fn ($l) => (int) $l['product']->seller_id === $ownerId && in_array((int) $l['product']->id, $chosen, true)));
                foreach ($codes->allocate($promoDiscount, array_map(fn ($i) => $lineTotals[$i], $mine)) as $k => $part) {
                    $promoShares[$mine[$k]] = $part;
                }
                $pointShares = $codes->allocate($discount - $promoDiscount, $lineTotals);
            } else {
                $pointShares = $codes->allocate($discount, $lineTotals);
            }
            $shares = array_map(fn ($i) => round($promoShares[$i] + $pointShares[$i], 2), array_keys($lines));

            $items = $fee = 0.0;
            foreach ($lines as $idx => $line) {
                $total = round((float) $line['price']['total'] * $line['quantity'], 2);
                $sellerKey = (int) $line['product']->seller_id;
                $shipping = isset($charged[$sellerKey]) ? 0.0 : (app(\App\Services\ShippingFees::class)->forSeller($market, $perSeller[$sellerKey]) + ($express['fee'] ?? 0.0));
                $charged[$sellerKey] = true;
                $shippingTotal += $shipping;
                $order = Order::create([
                    'company_id' => $user->company_id, 'customer_id' => $user->id, 'group_id' => $group->id, 'product_id' => $line['product']->id, 'quantity' => $line['quantity'],
                    'subject_type' => $line['vehicle'] ? 'vehicle_model' : null, 'subject_id' => $line['vehicle']?->vehicle_model_id,
                    'status' => OrderStatus::AwaitingPayment, 'product_amount' => $total, 'service_amount' => 0, 'commission_amount' => 0, 'total_amount' => $total,
                    'currency' => $line['price']['currency'], 'shipping_address' => $ship, 'shipping_amount' => $shipping, 'discount_amount' => $shares[$idx], 'seller_discount_amount' => $ownerId ? $promoShares[$idx] : 0, 'promo_code' => $promo?->code,
                ] + $extras + $this->markets->taxFor($company, $total) + $this->fees->forInvoice($total));
                OrderEvent::record($order, 'created', $user, ['direct_sale' => true, 'group_id' => $group->id, 'quantity' => $line['quantity']] + ($line['vehicle'] ? ['vehicle' => $line['vehicle']->label()] : []));
                $items += (float) $order->total_amount;
                $fee += (float) $order->service_fee_amount;
            }

            $group->update(['items_amount' => round($items, 2), 'service_fee_amount' => round($fee, 2), 'shipping_amount' => round($shippingTotal, 2), 'discount_amount' => round($discount, 2), 'payable_amount' => round($items + $fee + $shippingTotal - $discount, 2)]);
            if ($promo && $promoDiscount > 0) {
                \Illuminate\Support\Facades\DB::table('promo_redemptions')->insert(['promo_code_id' => $promo->id, 'user_id' => $user->id, 'group_id' => $group->id, 'amount' => $promoDiscount, 'status' => 'reserved', 'created_at' => now(), 'updated_at' => now()]);
            }
            $loyalty->spend($user, $pointsUsed, $group);
            $this->carts->clear($user);

            return $group->fresh();
        });
    }
}
