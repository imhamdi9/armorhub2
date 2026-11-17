<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductQuote;
use App\Models\ProductQuoteInvite;
use App\Models\ProductQuoteRequest;
use App\Services\Quotes\ProductQuoteService;
use Illuminate\Http\Request;

/** Asking for a price (customer) and answering with a quote (seller). */
class QuoteRequestController extends Controller
{
    public function __construct(private ProductQuoteService $quotes)
    {
    }

    // ------------------------------------------------------------------ customer

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'nullable|integer', 'category' => 'nullable|string|max:80', 'title' => 'nullable|string|max:160', 'quantity' => 'required|integer|min:1|max:500',
            'vehicle_id' => 'nullable|integer', 'notes' => 'nullable|string|max:600',
        ]);

        return response()->json($this->forCustomer($this->quotes->request($request->user(), $data)), 201);
    }

    public function index(Request $request)
    {
        return ProductQuoteRequest::where('customer_id', $request->user()->id)->latest('id')->limit(50)->get()->map(fn ($r) => $this->forCustomer($r))->values();
    }

    public function show(Request $request, int $id)
    {
        return $this->forCustomer($this->mine($request, $id));
    }

    public function accept(Request $request, int $id)
    {
        $req = $this->mine($request, $id);
        $data = $request->validate([
            'quote_id' => 'required|integer',
            'shipping_address' => 'required|array', 'shipping_address.name' => 'required|string|max:120', 'shipping_address.phone' => 'required|string|max:30',
            'shipping_address.line1' => 'required|string|max:200', 'shipping_address.line2' => 'nullable|string|max:200', 'shipping_address.city' => 'required|string|max:100',
            'shipping_address.region' => 'nullable|string|max:100', 'shipping_address.postal_code' => 'nullable|string|max:20',
        ]);
        $quote = ProductQuote::where('request_id', $req->id)->findOrFail($data['quote_id']);
        $group = $this->quotes->accept($req, $quote, $request->user(), $data['shipping_address']);

        // The customer pays the group like any purchase (POST /order-groups/{id}/payments).
        return response()->json(['order_group_id' => $group->id, 'payable_amount' => (float) $group->payable_amount, 'currency' => $group->currency], 201);
    }

    public function close(Request $request, int $id)
    {
        $req = $this->mine($request, $id);
        abort_unless($req->status === 'open', 422, 'Only an open request can be closed.');
        $req->update(['status' => 'closed']);

        return $this->forCustomer($req->fresh());
    }

    private function mine(Request $request, int $id): ProductQuoteRequest
    {
        return ProductQuoteRequest::where('customer_id', $request->user()->id)->findOrFail($id);
    }

    /** @return array<string, mixed> */
    private function forCustomer(ProductQuoteRequest $r): array
    {
        $now = now();

        return [
            'id' => $r->id, 'number' => $r->number, 'title' => $r->title, 'category' => $r->category, 'quantity' => $r->quantity, 'status' => $r->status === 'open' && $r->expires_at->isPast() ? 'expired' : $r->status,
            'notes' => $r->notes, 'expires_at' => $r->expires_at->toIso8601String(), 'product_id' => $r->product_id,
            // Quotes are shown cheapest first, each with what the customer needs to compare: price, time, condition, warranty and how long it holds.
            'quotes' => $r->quotes()->whereIn('status', ['submitted', 'accepted'])->orderBy('unit_price')->get()->map(fn ($q) => [
                'id' => $q->id, 'number' => $q->number, 'unit_price' => (float) $q->unit_price, 'total' => round((float) $q->unit_price * $r->quantity, 2), 'lead_days' => $q->lead_days,
                'condition' => $q->condition, 'warranty_days' => $q->warranty_days, 'note' => $q->note, 'valid_until' => $q->valid_until->toIso8601String(), 'expired' => $q->valid_until->lt($now),
                'status' => $q->status, 'product_id' => $q->product_id, 'seller' => 'S-'.strtoupper(substr(md5('seller'.$q->seller_id), 0, 4)),
            ])->values(),
        ];
    }

    // ------------------------------------------------------------------ seller

    private function seller(Request $request): int
    {
        abort_unless($request->user()->company_id, 422, 'Your account is not linked to a company.');

        return (int) $request->user()->company_id;
    }

    public function sellerIndex(Request $request)
    {
        $seller = $this->seller($request);
        $ids = ProductQuoteInvite::where('seller_id', $seller)->pluck('request_id');

        return ProductQuoteRequest::whereIn('id', $ids)->where('status', 'open')->where('expires_at', '>', now())->latest('id')->limit(100)->get()->map(function ($r) use ($seller) {
            $mine = ProductQuote::where('request_id', $r->id)->where('seller_id', $seller)->first();

            // What the seller needs to answer; never who the customer is.
            return ['id' => $r->id, 'number' => $r->number, 'title' => $r->title, 'category' => $r->category, 'quantity' => $r->quantity, 'notes' => $r->notes, 'product_id' => $r->product_id,
                'expires_at' => $r->expires_at->toIso8601String(), 'my_quote' => $mine ? ['number' => $mine->number, 'unit_price' => (float) $mine->unit_price, 'status' => $mine->status, 'valid_until' => $mine->valid_until->toIso8601String()] : null];
        })->values();
    }

    public function sellerQuote(Request $request, int $id)
    {
        $seller = $this->seller($request);
        $req = ProductQuoteRequest::findOrFail($id);
        $data = $request->validate([
            'product_id' => 'nullable|integer', 'unit_price' => 'required|numeric|min:0.01|max:10000000', 'available_qty' => 'nullable|integer|min:1|max:100000', 'lead_days' => 'nullable|integer|min:0|max:180',
            'condition' => 'nullable|in:new,oem,aftermarket,used', 'warranty_days' => 'nullable|integer|min:0|max:3650', 'note' => 'nullable|string|max:300', 'valid_days' => 'nullable|integer|min:1|max:'.ProductQuoteService::MAX_QUOTE_DAYS,
        ]);
        $q = $this->quotes->quote($req, $seller, $data);

        return response()->json(['id' => $q->id, 'number' => $q->number, 'unit_price' => (float) $q->unit_price, 'valid_until' => $q->valid_until->toIso8601String(), 'status' => $q->status], 201);
    }

    public function sellerWithdraw(Request $request, int $id)
    {
        $q = ProductQuote::where('request_id', $id)->where('seller_id', $this->seller($request))->where('status', 'submitted')->firstOrFail();
        $q->update(['status' => 'withdrawn']);

        return response()->json(['status' => 'withdrawn']);
    }
}
