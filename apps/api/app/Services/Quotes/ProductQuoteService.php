<?php

namespace App\Services\Quotes;

use App\Enums\OrderStatus;
use App\Models\Company;
use App\Models\CustomerVehicle;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\OrderGroup;
use App\Models\Product;
use App\Models\ProductQuote;
use App\Models\ProductQuoteInvite;
use App\Models\ProductQuoteRequest;
use App\Models\User;
use App\Services\Commission\CustomerFee;
use App\Services\DocumentNumbers;
use App\Services\Fitment\FitmentService;
use App\Services\InstallationNotifier;
use App\Services\SubscriptionService;
use App\Services\Warehouse\WarehouseService;
use App\Support\ContactGuard;
use App\Support\Markets\Markets;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Buying against a quote. A customer asks for a price, either for a product sold only on request or for a part nobody lists (sourcing). The sellers
 * who could supply it are asked; each answers with a numbered quote that is valid for a time; the customer accepts one, and that becomes an order
 * paid like any other. A quote is a promise: it holds its price and quantity until it expires.
 */
class ProductQuoteService
{
    public const REQUEST_DAYS = 7;
    public const MAX_QUOTE_DAYS = 30;

    public function __construct(
        private DocumentNumbers $numbers, private Markets $markets, private CustomerFee $fees, private InstallationNotifier $notify,
        private FitmentService $fitment, private SubscriptionService $plans, private WarehouseService $stock,
    ) {
    }

    /** @param array{product_id?: ?int, category?: ?string, title?: ?string, quantity: int, vehicle_id?: ?int, notes?: ?string} $data */
    public function request(User $customer, array $data): ProductQuoteRequest
    {
        $company = $customer->company;
        $market = $this->markets->forCompany($company)?->code;
        $product = null;

        if (! empty($data['product_id'])) {
            $product = Product::visibleInTenant($company?->tenant_id)->whereNotNull('seller_id')->where('is_active', true)->find($data['product_id'])
                ?? throw ValidationException::withMessages(['product_id' => ['This product is not available.']]);
            if ($product->market !== null && $product->market !== $market) {
                throw ValidationException::withMessages(['product_id' => ['This product is not available.']]);
            }
        } elseif (empty($data['category']) || empty($data['title'])) {
            throw ValidationException::withMessages(['title' => ['Say what you need and choose its category, so the right sellers are asked.']]);
        }

        foreach ([$data['title'] ?? '', $data['notes'] ?? ''] as $text) {
            if (ContactGuard::contains($text)) {
                throw ValidationException::withMessages(['notes' => ['Please do not put phone numbers, links or contact details here. They are shared after you accept a quote.']]);
            }
        }

        $vehicle = ! empty($data['vehicle_id']) ? (CustomerVehicle::where('customer_id', $customer->id)->find($data['vehicle_id']) ?? throw ValidationException::withMessages(['vehicle_id' => ['This car is not in your garage.']])) : null;

        $sellers = $product ? collect([(int) $product->seller_id]) : $this->sellersFor($company, (string) $data['category'], $market);
        if ($sellers->isEmpty()) {
            throw ValidationException::withMessages(['category' => ['No seller in your market sells that kind of part yet.']]);
        }

        return DB::transaction(function () use ($customer, $data, $product, $vehicle, $sellers) {
            $req = ProductQuoteRequest::create([
                'number' => $this->numbers->next('RFQ'), 'company_id' => $customer->company_id, 'customer_id' => $customer->id, 'product_id' => $product?->id,
                'category' => $product?->category ?? $data['category'] ?? null, 'title' => trim(strip_tags($data['title'] ?? $product?->name)), 'quantity' => (int) $data['quantity'],
                'vehicle_id' => $vehicle?->id, 'notes' => isset($data['notes']) ? trim(strip_tags($data['notes'])) : null, 'status' => 'open', 'expires_at' => now()->addDays(self::REQUEST_DAYS),
            ]);

            foreach ($sellers as $sellerId) {
                ProductQuoteInvite::firstOrCreate(['request_id' => $req->id, 'seller_id' => $sellerId]);
                $this->tellSeller($sellerId, 'A customer asked for a quote', "{$req->number}: {$req->title} x {$req->quantity}", '/dashboard/quote-requests');
            }

            return $req;
        });
    }

    /** Active sellers of the market that already sell something in the category. */
    private function sellersFor(?Company $store, string $category, ?string $market)
    {
        return Product::visibleInTenant($store?->tenant_id)->whereNotNull('seller_id')->where('is_active', true)->where('category', $category)
            ->when($market, fn ($q) => $q->where(fn ($w) => $w->whereNull('market')->orWhere('market', $market)))->distinct()->pluck('seller_id')->map(fn ($id) => (int) $id)->values();
    }

    /** @param array{product_id?: ?int, unit_price: float|int|string, available_qty?: ?int, lead_days?: ?int, condition?: ?string, warranty_days?: ?int, note?: ?string, valid_days?: ?int} $data */
    public function quote(ProductQuoteRequest $req, int $sellerId, array $data): ProductQuote
    {
        $this->assertOpen($req);
        if (! ProductQuoteInvite::where('request_id', $req->id)->where('seller_id', $sellerId)->exists()) {
            abort(404);
        }

        // The quote names one of the seller's own, active products: that is what the customer will receive and what the order is made for.
        $productId = $req->product_id ?? ($data['product_id'] ?? null);
        $product = Product::withoutGlobalScopes()->where('seller_id', $sellerId)->where('is_active', true)->find($productId)
            ?? throw ValidationException::withMessages(['product_id' => ['Choose one of your own products that fills this request.']]);

        if (ContactGuard::contains($data['note'] ?? null)) {
            throw ValidationException::withMessages(['note' => ['Please do not put phone numbers, links or contact details in a quote.']]);
        }
        if (isset($data['available_qty']) && $data['available_qty'] < $req->quantity) {
            throw ValidationException::withMessages(['available_qty' => ["The customer needs {$req->quantity}. Quote only what you can supply in full, or ask them to change the quantity."]]);
        }
        $days = min(self::MAX_QUOTE_DAYS, max(1, (int) ($data['valid_days'] ?? 7)));

        return DB::transaction(function () use ($req, $sellerId, $product, $data, $days) {
            $quote = ProductQuote::where('request_id', $req->id)->where('seller_id', $sellerId)->first();
            if ($quote && $quote->status === 'accepted') {
                throw ValidationException::withMessages(['request' => ['This quote was already accepted.']]);
            }

            $quote = ProductQuote::updateOrCreate(['request_id' => $req->id, 'seller_id' => $sellerId], [
                'number' => $quote?->number ?? $this->numbers->next('QT'), 'product_id' => $product->id, 'unit_price' => $data['unit_price'], 'available_qty' => $data['available_qty'] ?? null,
                'lead_days' => (int) ($data['lead_days'] ?? 0), 'condition' => $data['condition'] ?? 'new', 'warranty_days' => $data['warranty_days'] ?? null,
                'note' => isset($data['note']) ? trim(strip_tags($data['note'])) : null, 'valid_until' => now()->addDays($days), 'status' => 'submitted',
            ]);
            $this->notify->user($req->customer_id, 'You received a quote', ':number for :title', "/dashboard/quote-requests/{$req->id}", ['number' => (string) $quote->number, 'title' => (string) $req->title]);

            return $quote;
        });
    }

    /** Accepting a quote makes the order: the quoted price times the quantity, to be paid once (an order group of one). */
    /** @param array{name: string, phone: string, line1: string, line2?: ?string, city: string, region?: ?string, postal_code?: ?string} $address */
    public function accept(ProductQuoteRequest $req, ProductQuote $quote, User $customer, array $address): OrderGroup
    {
        $this->assertOpen($req);
        if ((int) $quote->request_id !== (int) $req->id || $quote->status !== 'submitted') {
            throw ValidationException::withMessages(['quote_id' => ['That quote cannot be accepted.']]);
        }
        if ($quote->valid_until->isPast()) {
            throw ValidationException::withMessages(['quote_id' => ['That quote has expired. Ask the seller for a new one.']]);
        }

        $product = Product::withoutGlobalScopes()->findOrFail($quote->product_id);
        if (! $product->is_active) {
            throw ValidationException::withMessages(['quote_id' => ['That product is no longer available.']]);
        }
        $this->stock->assertInStock($product, $req->quantity);
        $this->plans->assertCanTakeCustomers('seller', (int) $quote->seller_id);

        $vehicle = $req->vehicle_id ? CustomerVehicle::find($req->vehicle_id) : null;
        $this->fitment->assertCanBuy($product, $vehicle);

        $company = $customer->company;
        $address = $this->normalise($customer, $address);

        return DB::transaction(function () use ($req, $quote, $product, $customer, $company, $vehicle, $address) {
            $total = round((float) $quote->unit_price * $req->quantity, 2);
            // Delivery costs the same as for any purchase from this seller in this market.
            $shipping = $product->requires_shipping ? app(\App\Services\ShippingFees::class)->forSeller($this->markets->forCompany($company)?->code, $total) : 0.0;
            $group = OrderGroup::create(['company_id' => $customer->company_id, 'customer_id' => $customer->id, 'currency' => $this->markets->forCompany($company)?->currency() ?? (string) config('platform.currency'),
                'items_amount' => 0, 'service_fee_amount' => 0, 'payable_amount' => 0, 'status' => OrderGroup::AWAITING, 'shipping_address' => $address]);

            $order = Order::create([
                'company_id' => $customer->company_id, 'customer_id' => $customer->id, 'group_id' => $group->id, 'product_id' => $product->id, 'quantity' => $req->quantity,
                'subject_type' => $vehicle ? 'vehicle_model' : null, 'subject_id' => $vehicle?->vehicle_model_id, 'status' => OrderStatus::AwaitingPayment, 'product_amount' => $total,
                'service_amount' => 0, 'commission_amount' => 0, 'total_amount' => $total, 'currency' => $group->currency, 'shipping_address' => $address, 'shipping_amount' => $shipping,
            ] + $this->markets->taxFor($company, $total) + $this->fees->forInvoice($total));
            OrderEvent::record($order, 'created', $customer, ['direct_sale' => true, 'quote' => $quote->number, 'request' => $req->number]);

            $group->update(['items_amount' => (float) $order->total_amount, 'service_fee_amount' => (float) $order->service_fee_amount, 'shipping_amount' => $shipping, 'payable_amount' => $order->payable_amount]);
            $quote->update(['status' => 'accepted']);
            ProductQuote::where('request_id', $req->id)->where('id', '!=', $quote->id)->where('status', 'submitted')->update(['status' => 'rejected']);
            $req->update(['status' => 'accepted', 'accepted_quote_id' => $quote->id]);

            $this->tellSeller((int) $quote->seller_id, 'Your quote was accepted', "{$quote->number}: the customer will pay and you will be asked to ship.", '/dashboard/seller-orders');

            return $group->fresh();
        });
    }

    private function assertOpen(ProductQuoteRequest $req): void
    {
        if ($req->status === 'open' && $req->expires_at->isPast()) {
            $req->update(['status' => 'expired']);
        }
        if ($req->status !== 'open') {
            throw ValidationException::withMessages(['request' => ['This request is no longer open.']]);
        }
    }

    /** The delivery address, with the phone checked for the customer's country. */
    private function normalise(User $customer, array $a): array
    {
        $phone = $this->markets->phoneFor($customer->company, $a['phone']) ?? throw ValidationException::withMessages(['shipping_address.phone' => ['Please enter a valid mobile number for delivery.']]);

        return ['name' => $a['name'], 'phone' => $phone, 'line1' => $a['line1'], 'line2' => $a['line2'] ?? null, 'city' => $a['city'], 'region' => $a['region'] ?? null,
            'postal_code' => $a['postal_code'] ?? null, 'country' => $this->markets->forCompany($customer->company)?->code ?? 'EG'];
    }

    private function tellSeller(int $companyId, string $title, string $body, string $path): void
    {
        foreach (User::where('company_id', $companyId)->role('seller')->pluck('id') as $id) {
            $this->notify->user((int) $id, $title, $body, $path);
        }
    }
}
