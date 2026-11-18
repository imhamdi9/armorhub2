<?php

namespace App\Services\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CustomerVehicle;
use App\Models\Product;
use App\Models\ProductPriceRule;
use App\Models\User;
use App\Services\Fitment\FitmentService;
use App\Services\Pricing\CalculateProductPriceService;
use App\Services\Warehouse\WarehouseService;
use App\Support\Markets\Markets;
use Illuminate\Validation\ValidationException;

/**
 * What a customer has set aside to buy together. Only goods sold by sellers go in a cart (a service is quoted, an installation is asked for):
 * every line is checked as strictly as a single order would be, when it is added and again when the cart is bought.
 */
class CartService
{
    public const MAX_QUANTITY = 50;
    public const MAX_LINES = 30;

    public function __construct(private CalculateProductPriceService $pricing, private WarehouseService $stock, private Markets $markets, private FitmentService $fitment)
    {
    }

    /** How the customer may have goods delivered: the usual way, and a faster one when the market offers it (its fee is added per seller). @return list<array<string, mixed>> */
    private function deliveryOptions(\App\Services\ShippingFees $shipping, ?string $market, int $sellers, ?int $eta): array
    {
        $options = [['key' => 'standard', 'extra_fee' => 0.0, 'eta_days' => $eta]];
        if ($express = $shipping->express($market)) {
            $options[] = ['key' => 'express', 'extra_fee' => round($express['fee'] * $sellers, 2), 'eta_days' => $express['eta_days']];
        }

        return $options;
    }

    public function cartOf(User $user): Cart
    {
        return Cart::firstOrCreate(['customer_id' => $user->id]);
    }

    public function add(User $user, int $productId, ?int $optionId, int $quantity, ?int $vehicleId = null): CartItem
    {
        $cart = $this->cartOf($user);
        $line = $this->resolve($user, $productId, $optionId, $quantity, $vehicleId);

        $existing = CartItem::where('cart_id', $cart->id)->where('product_id', $productId)->where('target_type', $line['rule']->target_type)->where('target_id', $line['rule']->target_id)->first();
        if (! $existing && $cart->items()->count() >= self::MAX_LINES) {
            throw ValidationException::withMessages(['cart' => ['Your cart is full. Buy or remove something first.']]);
        }

        $total = min(self::MAX_QUANTITY, ($existing?->quantity ?? 0) + $quantity);
        // Adding more of something already in the cart must still be in stock.
        $this->stock->assertInStock($line['product'], $total);

        return CartItem::updateOrCreate(
            ['cart_id' => $cart->id, 'product_id' => $productId, 'target_type' => $line['rule']->target_type, 'target_id' => $line['rule']->target_id],
            ['quantity' => $total, 'vehicle_id' => $vehicleId ?? $existing?->vehicle_id],
        );
    }

    public function setQuantity(User $user, int $itemId, int $quantity): CartItem
    {
        $item = CartItem::whereIn('cart_id', Cart::where('customer_id', $user->id)->select('id'))->findOrFail($itemId);
        $line = $this->resolve($user, $item->product_id, $item->target_id, $quantity, $item->vehicle_id);
        $this->stock->assertInStock($line['product'], $quantity);
        $item->update(['quantity' => $quantity]);

        return $item;
    }

    public function remove(User $user, int $itemId): void
    {
        CartItem::whereIn('cart_id', Cart::where('customer_id', $user->id)->select('id'))->whereKey($itemId)->delete();
    }

    /** Applies a discount code to the cart (checked against what is in it now; refused with the reason if it does not qualify). */
    public function setPromo(User $user, string $code): void
    {
        $cart = $this->cartOf($user);
        $view = $this->view($user, ignorePromo: true);
        $market = app(\App\Support\Markets\Markets::class)->forCompany($user->company)?->code;
        $checked = app(\App\Services\PromoCodes::class)->check($code, $user, (float) $view['items_amount'], $market, $view['by_product'] ?? null);
        $cart->update(['promo_code' => $checked['code']->code]);
    }

    public function clearPromo(User $user): void
    {
        $this->cartOf($user)->update(['promo_code' => null]);
    }

    public function clear(User $user): void
    {
        CartItem::whereIn('cart_id', Cart::where('customer_id', $user->id)->select('id'))->delete();
        Cart::where('customer_id', $user->id)->update(['promo_code' => null]);
    }

    /**
     * The cart as the customer sees it, priced now. A line that can no longer be bought is shown with the reason instead of vanishing.
     *
     * @return array{items: list<array<string, mixed>>, currency: ?string, items_amount: float, service_fee_amount: float, payable_amount: float, can_checkout: bool}
     */
    public function view(User $user, bool $ignorePromo = false): array
    {
        $items = [];
        $currency = null;
        $sum = $fee = 0.0;
        $ok = true;
        $bySeller = [];
        $byProduct = [];
        $allGoods = true;

        foreach ($this->cartOf($user)->items()->with('product')->get() as $item) {
            $row = ['id' => $item->id, 'product_id' => $item->product_id, 'name' => $item->product?->name, 'image' => ($item->product?->images ?? [])[0] ?? null,
                'quantity' => $item->quantity, 'seller_id' => $item->product?->seller_id];
            try {
                $line = $this->resolve($user, $item->product_id, $item->target_id, $item->quantity, $item->vehicle_id);
                $unit = (float) $line['price']['total'];
                $feeRate = app(\App\Services\Commission\CustomerFee::class)->forInvoice($unit * $item->quantity);
                $row += ['option' => $line['rule']->target_type.':'.$line['rule']->target_id, 'unit_price' => $unit, 'line_total' => round($unit * $item->quantity, 2), 'currency' => $line['price']['currency'], 'problem' => null, 'fit' => $this->fitment->checkVehicle($line['product'], $line['vehicle']), 'vehicle' => $line['vehicle']?->label()];
                $currency ??= $line['price']['currency'];
                $sum += $row['line_total'];
                $allGoods = $allGoods && (bool) $item->product?->requires_shipping;
                $bySeller[(int) $item->product?->seller_id] = ($bySeller[(int) $item->product?->seller_id] ?? 0.0) + $row['line_total'];
                $byProduct[(int) $item->product_id] = ($byProduct[(int) $item->product_id] ?? 0.0) + $row['line_total'];
                $fee += $feeRate['service_fee_amount'];
            } catch (ValidationException $e) {
                $row += ['problem' => collect($e->errors())->flatten()->first(), 'unit_price' => null, 'line_total' => null, 'currency' => null];
                $ok = false;
            }
            $items[] = $row;
        }

        // Delivery: a flat fee for each seller's parcel, free above a threshold (set per market).
        $market = app(\App\Support\Markets\Markets::class)->forCompany($user->company)?->code;
        $shipping = app(\App\Services\ShippingFees::class);
        $shippingTotal = round(array_sum(array_map(fn ($sub) => $shipping->forSeller($market, $sub), $bySeller)), 2);
        $rules = $shipping->rules($market);

        // A discount code, if one is applied and still qualifies (otherwise it is dropped, with the reason shown once).
        $discount = 0.0;
        $promo = null;
        $promoProblem = null;
        $cart = $this->cartOf($user);
        if (! $ignorePromo && $cart->promo_code) {
            try {
                $checked = app(\App\Services\PromoCodes::class)->check($cart->promo_code, $user, round($sum, 2), $market, $byProduct);
                $discount = $checked['discount'];
                $promo = $checked['code']->code;
            } catch (ValidationException $e) {
                $promoProblem = collect($e->errors())->flatten()->first();
                $cart->update(['promo_code' => null]);
            }
        }

        return ['by_product' => $byProduct, 'items' => $items, 'currency' => $currency, 'items_amount' => round($sum, 2), 'service_fee_amount' => round($fee, 2), 'shipping_amount' => $shippingTotal,
            'discount_amount' => $discount, 'cod_available' => app(\App\Services\Payment\GroupPayments::class)->codAvailable(round($sum + $fee + $shippingTotal - $discount, 2), $allGoods && $items !== []), 'promo_code' => $promo, 'promo_problem' => $promoProblem,
            'points' => $allGoods && $items !== [] ? app(\App\Services\Loyalty::class)->redeemable($user, round($sum, 2)) : null, 'delivery_options' => $allGoods && $items !== [] ? $this->deliveryOptions($shipping, $market, count($bySeller), $rules['eta_days']) : [], 'eta_days' => $rules['eta_days'], 'free_shipping_over' => $rules['free_over'], 'payable_amount' => round($sum + $fee + $shippingTotal - $discount, 2), 'can_checkout' => $ok && $items !== []];
    }

    /**
     * Checks one line the way a direct order does, and prices it.
     *
     * @return array{product: Product, rule: ProductPriceRule, price: array<string, mixed>, vehicle: ?CustomerVehicle}
     */
    public function resolve(User $user, int $productId, ?int $optionId, int $quantity, ?int $vehicleId = null): array
    {
        if ($quantity < 1 || $quantity > self::MAX_QUANTITY) {
            throw ValidationException::withMessages(['quantity' => [__('Choose between 1 and :n.', ['n' => self::MAX_QUANTITY])]]);
        }
        if (! $user->company_id) {
            throw ValidationException::withMessages(['company' => ['Your account is not linked to a store.']]);
        }

        $company = $user->company;
        $market = $this->markets->forCompany($company)?->code;
        $product = Product::visibleInTenant($company?->tenant_id)->whereNotNull('seller_id')->where('requires_shipping', true)->find($productId);

        if (! $product || ! $product->is_active || ($product->market !== null && $product->market !== $market)
            || ($product->industry_id !== null && ! in_array($product->industry_id, $user->industryIds(), true))) {
            throw ValidationException::withMessages(['product_id' => ['This product is not available.']]);
        }
        if ($product->installation === 'required') {
            throw ValidationException::withMessages(['product_id' => ['This product is sold together with an installation offer, so it cannot go in a cart.']]);
        }
        if (($product->sale_mode ?? 'fixed') === 'quote') {
            throw ValidationException::withMessages(['product_id' => ['This product is sold on request: ask the seller for a quote.']]);
        }

        $rule = ProductPriceRule::where('product_id', $product->id)->where('is_active', true)->when($optionId, fn ($q, $id) => $q->where('target_id', $id))->orderBy('id')->first();
        $price = $rule ? $this->pricing->calculate($product->id, [$rule->target_id], $rule->target_type, $company) : null;
        if (! $rule || ($price['parts_count'] ?? 0) === 0) {
            throw ValidationException::withMessages(['product_id' => ['No price is defined for this product.']]);
        }

        // A part that has to fit is bought for one of the customer's own cars, and it must fit it.
        $vehicle = $vehicleId ? CustomerVehicle::where('customer_id', $user->id)->find($vehicleId) : null;
        if ($vehicleId && ! $vehicle) {
            throw ValidationException::withMessages(['vehicle_id' => ['This car is not in your garage.']]);
        }
        $this->fitment->assertCanBuy($product, $vehicle);

        return ['product' => $product, 'rule' => $rule, 'price' => $price, 'vehicle' => $vehicle];
    }
}
