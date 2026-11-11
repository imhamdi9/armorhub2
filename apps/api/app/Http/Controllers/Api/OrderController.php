<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Enums\QuoteStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\PartnerQuote;
use App\Modules\IndustryRegistry;
use App\Services\Location\FindNearbyPartnersService;
use App\Services\Pricing\CalculateProductPriceService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    use AuthorizesRequests;

    public function show(Order $order)
    {
        $this->authorize('view', $order);

        return response()->json($order->makeVisible('shipping_address')->load(['product', 'subject', 'events.user:id,name', 'warranty']));
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Order::class);

        $request->validate(['status' => 'nullable|string', 'per_page' => 'nullable|integer|min:1|max:100']);

        $user = $request->user();

        $query = Order::with('product')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest('id');

        if ($user->hasRole('customer')) {
            $query->where('customer_id', $user->id);
        } elseif (! $user->hasRole('platform-admin')) {
            $query->where('company_id', $user->company_id);
        }

        return $query->paginate($request->integer('per_page', 15));
    }

    public function store(
        Request $request,
        CalculateProductPriceService $pricing,
        FindNearbyPartnersService $location,
        IndustryRegistry $registry
    ) {
        $this->authorize('create', Order::class);

        $user = auth()->user();
        $settings = app(\App\Support\Settings\Settings::class);

        if (! $settings->bool('platform.orders_enabled')) {
            return response()->json(['message' => 'New orders are paused for now. Please try again later.'], 503);
        }

        if ($user->hasRole('customer') && ! $user->hasVerifiedEmail() && app(\App\Support\Security\EmailPolicy::class)->verificationRequired()) {
            return response()->json([
                'message' => 'Please verify your email address before ordering. Check your inbox for the link.',
                'code' => 'email_unverified',
            ], 403);
        }

        $open = Order::withoutGlobalScopes()->where('customer_id', $user->id)
            ->whereIn('status', ['pending_quotes', 'quotes_received', 'partner_selected', 'awaiting_payment'])->count();

        if ($open >= (int) $settings->get('security.max_open_orders')) {
            return response()->json([
                'message' => 'You have too many unpaid orders. Pay for or cancel one before placing another.',
            ], 422);
        }

        $data = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'subject_type' => ['nullable', 'string', 'in:'.implode(',', array_keys($registry->subjectModels()))],
            'subject_id' => ['nullable', 'integer', 'required_with:subject_type'],
            'target_type' => 'nullable|string',
            'targets' => 'required|array|min:1',
            'targets.*' => 'integer',
        ]);

        if (! $user->company_id) {
            throw ValidationException::withMessages([
                'company' => ['Your account is not linked to a company.'],
            ]);
        }

        if (! $user->location_enabled || ! $user->latitude || ! $user->longitude) {
            return response()->json([
                'message' => 'Please enable location to find nearby partners',
            ], 422);
        }

        // Customers can only order what their own industry offers, and only what is on sale.
        $product = \App\Models\Product::find($data['product_id']);
        $market = app(\App\Support\Markets\Markets::class)->forCompany($user->company)?->code;
        if (! $product->is_active || ($product->industry_id !== null && $product->industry_id !== $user->industryId())
            || ($product->market !== null && $product->market !== $market)) {
            throw ValidationException::withMessages(['product_id' => ['This product is not available.']]);
        }

        $address = $this->shippingAddress($request, $product, $user);

        $this->assertSubject($data, $registry, $user->id);

        $price = $pricing->calculate(
            $data['product_id'],
            $data['targets'],
            $data['target_type'] ?? null,
            $user->company
        );

        if ($price['parts_count'] === 0) {
            throw ValidationException::withMessages([
                'targets' => ['No price is defined for the selected options.'],
            ]);
        }

        $partners = $location->executeWithFallback(
            $user->latitude,
            $user->longitude,
            $user->city,
            $user->country
        );

        if ($partners->isEmpty()) {
            return response()->json(['message' => 'No partners available'], 422);
        }

        $order = DB::transaction(function () use ($user, $data, $price, $partners, $address) {

            $order = Order::create([
                'company_id' => $user->company_id,
                'customer_id' => $user->id,
                'product_id' => $data['product_id'],
                'subject_type' => $data['subject_type'] ?? null,
                'subject_id' => $data['subject_id'] ?? null,
                'status' => OrderStatus::PendingQuotes,
                'product_amount' => $price['total'],
                'service_amount' => 0,
                'commission_amount' => 0,
                'total_amount' => $price['total'],
                'currency' => $price['currency'],
                'shipping_address' => $address,
            ] + app(\App\Support\Markets\Markets::class)->taxFor($user->company, (float) $price['total']));

            foreach ($partners as $partner) {
                PartnerQuote::create([
                    'company_id' => $user->company_id,
                    'order_id' => $order->id,
                    'partner_id' => $partner->id,
                    'price' => 0,
                    'status' => QuoteStatus::Pending,
                ]);
            }

            OrderEvent::record($order, 'created', $user, ['partners_asked' => $partners->count()]);

            return $order;
        });

        return response()->json([
            'message' => 'Order created successfully',
            'order' => $order,
            'partner_quotes' => $partners->count(),
        ], 201);
    }

    /** Physical products need a delivery address (checked against the store's country); services do not. @return array<string, string>|null */
    private function shippingAddress(Request $request, \App\Models\Product $product, $user): ?array
    {
        if (! $product->requires_shipping) {
            return null;
        }

        $a = $request->validate([
            'shipping_address' => 'required|array',
            'shipping_address.name' => 'required|string|max:120',
            'shipping_address.phone' => 'required|string|max:30',
            'shipping_address.line1' => 'required|string|max:200',
            'shipping_address.line2' => 'nullable|string|max:200',
            'shipping_address.city' => 'required|string|max:100',
            'shipping_address.region' => 'nullable|string|max:100',
            'shipping_address.postal_code' => 'nullable|string|max:20',
            'shipping_address.country' => 'required|string|size:2|alpha',
        ])['shipping_address'];

        $phone = app(\App\Support\Markets\Markets::class)->phoneFor($user->company, $a['phone']);

        if (! $phone) {
            throw ValidationException::withMessages(['shipping_address.phone' => ['Please enter a valid mobile number for delivery.']]);
        }

        return array_merge($a, ['phone' => $phone, 'country' => strtoupper($a['country'])]);
    }

    /** The subject must exist, and customer-owned subjects must be the user's own. */
    private function assertSubject(array $data, IndustryRegistry $registry, int $userId): void
    {
        if (empty($data['subject_type'])) {
            return;
        }

        $class = $registry->subjectModels()[$data['subject_type']];
        $subject = $class::find($data['subject_id']);

        if (! $subject || (isset($subject->customer_id) && $subject->customer_id !== $userId)) {
            throw ValidationException::withMessages([
                'subject_id' => ['The selected item was not found.'],
            ]);
        }
    }
}
