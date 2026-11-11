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

        return response()->json($order->load(['product', 'subject', 'events.user:id,name', 'warranty']));
    }

    public function index()
    {
        $this->authorize('viewAny', Order::class);

        $user = auth()->user();

        $query = Order::with('product')->latest()->limit(200);

        if ($user->hasRole('platform-admin')) {
            return $query->get();
        }

        if ($user->hasRole('customer')) {
            return $query->where('customer_id', $user->id)->get();
        }

        return $query->where('company_id', $user->company_id)->get();
    }

    public function store(
        Request $request,
        CalculateProductPriceService $pricing,
        FindNearbyPartnersService $location,
        IndustryRegistry $registry
    ) {
        $this->authorize('create', Order::class);

        $user = auth()->user();

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

        $this->assertSubject($data, $registry, $user->id);

        $price = $pricing->calculate(
            $data['product_id'],
            $data['targets'],
            $data['target_type'] ?? null
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

        $order = DB::transaction(function () use ($user, $data, $price, $partners) {

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
            ]);

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
