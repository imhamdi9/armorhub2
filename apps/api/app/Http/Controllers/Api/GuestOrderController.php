<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductPriceRule;
use App\Services\OrderLifecycleService;
use App\Services\Payment\PaymentFinalizer;
use App\Services\Payment\PaymentGateway;
use App\Services\Payment\PaymentUnavailable;
use App\Services\Pricing\CalculateProductPriceService;
use App\Services\Storefront\StorefrontService;
use App\Support\Markets\Markets;
use App\Support\Security\BotGuard;
use App\Support\Settings\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Buying a seller's goods without an account. The buyer leaves a name, e-mail, mobile number and delivery address, pays, and follows the
 * order from a secret link (also e-mailed). Whoever holds the link can see and pay that one order, nothing else. An account is optional
 * and can be created afterwards: orders made with the same e-mail join it once the e-mail is verified.
 */
class GuestOrderController extends Controller
{
    public function __construct(private StorefrontService $stores)
    {
    }

    public function store(Request $request, BotGuard $bots, CalculateProductPriceService $pricing, Markets $markets, Settings $settings)
    {
        if (! $settings->bool('platform.orders_enabled')) {
            return response()->json(['message' => 'New orders are paused for now. Please try again later.'], 503);
        }
        if (! $bots->passes($request)) {
            return response()->json(['message' => 'Please try again.'], 422);
        }

        $data = $request->validate([
            'store' => 'required|string|max:80', 'product_id' => 'required|integer',
            'option_id' => 'nullable|integer', 'name' => 'required|string|max:120', 'email' => 'required|email:rfc|max:190',
            'phone' => 'required|string|max:30',
            'address.line1' => 'required|string|max:200', 'address.line2' => 'nullable|string|max:200', 'address.city' => 'required|string|max:100',
            'address.region' => 'nullable|string|max:100', 'address.postal_code' => 'nullable|string|max:20',
        ]);

        $company = $this->stores->find($data['store']) ?? abort(404);
        $market = $markets->forCompany($company);
        $product = Product::visibleInTenant($company->tenant_id)->whereNotNull('seller_id')->where('requires_shipping', true)->find($data['product_id']);
        if (! $product || ! $product->is_active || ($product->market !== null && $product->market !== $market?->code)
            || ($product->industry_id !== null && $product->industry_id !== $company->tenant?->industry_id)) {
            throw ValidationException::withMessages(['product_id' => ['This product is not available.']]);
        }

        // "Sold only with an installation offer": offers come from centres to a signed-in customer, so a guest cannot buy it directly.
        if ($product->installation === 'required') {
            throw ValidationException::withMessages(['product_id' => ['This product is sold together with an installation offer. Sign in or create an account to get offers first.']]);
        }

        $phone = $markets->phoneFor($company, $data['phone']);
        if (! $phone) {
            throw ValidationException::withMessages(['phone' => ['Please enter a valid mobile number for delivery.']]);
        }

        // Goods have one price option ("the whole thing"); a buyer may pick another when the seller defined several.
        $rule = ProductPriceRule::where('product_id', $product->id)->where('is_active', true)->when($data['option_id'] ?? null, fn ($q, $id) => $q->where('target_id', $id))->orderBy('id')->first();
        if (! $rule) {
            throw ValidationException::withMessages(['product_id' => ['No price is defined for this product.']]);
        }
        $price = $pricing->calculate($product->id, [$rule->target_id], $rule->target_type, $company);
        if ($price['parts_count'] === 0) {
            throw ValidationException::withMessages(['product_id' => ['No price is defined for this product.']]);
        }

        $address = ['name' => $data['name'], 'phone' => $phone, 'line1' => $data['address']['line1'], 'line2' => $data['address']['line2'] ?? null, 'city' => $data['address']['city'],
            'region' => $data['address']['region'] ?? null, 'postal_code' => $data['address']['postal_code'] ?? null, 'country' => $market?->code ?? 'EG'];

        $order = DB::transaction(function () use ($company, $product, $price, $data, $address, $markets) {
            $order = Order::create([
                'company_id' => $company->id, 'customer_id' => null, 'product_id' => $product->id, 'status' => OrderStatus::AwaitingPayment,
                'product_amount' => $price['total'], 'service_amount' => 0, 'commission_amount' => 0, 'total_amount' => $price['total'], 'currency' => $price['currency'],
                'shipping_address' => $address, 'guest_name' => $data['name'], 'guest_email' => strtolower($data['email']), 'guest_token' => Str::random(48),
            ] + $markets->taxFor($company, (float) $price['total']));
            OrderEvent::record($order, 'created', null, ['direct_sale' => true, 'guest' => true]);

            return $order;
        });

        return response()->json(['token' => $order->guest_token] + $this->view($order), 201);
    }

    public function show(string $token)
    {
        return $this->view($this->find($token));
    }

    public function pay(Request $request, string $token, PaymentFinalizer $finalizer, PaymentGateway $gateway)
    {
        $order = $this->find($token);
        $request->validate(['payment_method' => 'nullable|string|max:40']);

        if ($order->status === OrderStatus::Paid) {
            return response()->json(['message' => 'Order already paid'], 422);
        }
        if ($order->status !== OrderStatus::AwaitingPayment) {
            throw ValidationException::withMessages(['order' => ['This order cannot be paid.']]);
        }

        try {
            $charge = $gateway->charge($order, (float) $order->total_amount, $request->input('payment_method'));
        } catch (PaymentUnavailable $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        if ($charge['status'] === 'failed') {
            return response()->json(['message' => $charge['message'] ?? 'The payment was declined.'], 402);
        }

        if ($charge['status'] === 'redirect') {
            Payment::create(['company_id' => $order->company_id, 'order_id' => $order->id, 'amount' => $order->total_amount, 'currency' => $order->currency,
                'payment_method' => $request->input('payment_method'), 'status' => 'pending', 'provider' => $charge['provider'] ?? null, 'transaction_id' => $charge['transaction_id']]);

            return response()->json(['message' => 'Continue to the payment page.', 'checkout_url' => $charge['url']], 202);
        }

        $finalizer->complete($order, $charge, $request->input('payment_method'), null);

        return response()->json(['message' => 'Payment completed'] + $this->view($order->fresh()));
    }

    public function cancel(string $token, OrderLifecycleService $lifecycle)
    {
        $order = $this->find($token);
        $lifecycle->cancel($order, null, 'Cancelled by the buyer');

        return $this->view($order->fresh());
    }

    private function find(string $token): Order
    {
        abort_unless(strlen($token) === 48, 404);

        return Order::withoutGlobalScopes()->with('product')->where('guest_token', $token)->firstOrFail();
    }

    /** The order as its buyer may see it: what, how much, where it goes, how it is going. Never other people's data. */
    private function view(Order $order): array
    {
        $order->loadMissing('product');
        $company = \App\Models\Company::withoutGlobalScopes()->find($order->company_id);
        $status = $order->status instanceof OrderStatus ? $order->status->value : (string) $order->status;

        return [
            'id' => $order->id, 'status' => $status, 'currency' => $order->currency, 'total' => (float) $order->total_amount, 'tax_amount' => $order->tax_amount !== null ? (float) $order->tax_amount : null,
            'created_at' => $order->created_at?->toIso8601String(), 'email' => $order->guest_email, 'name' => $order->guest_name,
            'product' => ['installation' => $order->product?->installation ?? 'none', 'name' => $order->product?->name, 'image' => $order->product?->images[0] ?? null, 'seller' => $order->product?->seller_id ? \App\Models\Company::withoutGlobalScopes()->whereKey($order->product->seller_id)->value('name') : null],
            'store' => ['name' => $company?->name, 'slug' => $company?->slug],
            'shipping_address' => $order->makeVisible('shipping_address')->shipping_address,
            'can_pay' => $status === OrderStatus::AwaitingPayment->value, 'can_cancel' => $order->status->isCancellable(),
            'shipments' => \App\Models\Shipment::withoutGlobalScopes()->where('order_id', $order->id)->latest('id')->get(['carrier', 'tracking_number', 'tracking_url', 'status'])->all(),
            'timeline' => OrderEvent::where('order_id', $order->id)->orderBy('id')->get(['type', 'created_at'])->map(fn ($e) => ['type' => $e->type, 'at' => $e->created_at?->toIso8601String()])->all(),
        ];
    }
}
