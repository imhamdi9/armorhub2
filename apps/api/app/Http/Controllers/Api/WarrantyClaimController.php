<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\User;
use App\Models\Warranty;
use App\Models\WarrantyClaim;
use App\Notifications\SimpleNotice;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Claims under a warranty. The customer describes the problem while the warranty is running (one open claim at a time); the selling company
 * (or a platform administrator) approves it with a repair, a replacement or a refund, or declines it with a reason, and later marks it resolved.
 */
class WarrantyClaimController extends Controller
{
    public function store(Request $request, int $id)
    {
        $user = $request->user();
        $warranty = Warranty::withoutGlobalScopes()->where('customer_id', $user->id)->findOrFail($id);
        $data = $request->validate(['description' => 'required|string|min:10|max:600']);

        if (! $warranty->ends_at->isFuture()) {
            throw ValidationException::withMessages(['warranty' => ['This warranty has ended.']]);
        }
        if (WarrantyClaim::where('warranty_id', $warranty->id)->whereIn('status', [WarrantyClaim::OPEN, WarrantyClaim::APPROVED])->exists()) {
            throw ValidationException::withMessages(['warranty' => ['You already have a claim in progress for this warranty.']]);
        }

        $claim = WarrantyClaim::create(['warranty_id' => $warranty->id, 'customer_id' => $user->id, 'product_id' => $warranty->product_id, 'description' => trim(strip_tags($data['description'])), 'status' => WarrantyClaim::OPEN]);

        $product = Product::withoutGlobalScopes()->find($warranty->product_id);
        if ($product?->seller_id) {
            User::where('company_id', $product->seller_id)->where('is_active', true)->get()->filter(fn (User $u) => $u->can('products.update'))
                ->each(fn (User $u) => $u->notify(new SimpleNotice('Warranty claim: :product', $claim->description, '/dashboard/warranty-claims', ['product' => $product->name])));
        }

        return response()->json($this->row($claim), 201);
    }

    /** Claims on the products this person sells (every claim for an administrator), open ones first. */
    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user->hasRole('platform-admin') || ($user->company_id && $user->can('products.update')), 403);

        return WarrantyClaim::with(['product:id,name,name_ar,seller_id', 'customer:id,name'])
            ->when(! $user->hasRole('platform-admin'), fn ($q) => $q->whereIn('product_id', Product::withoutGlobalScopes()->where('seller_id', $user->company_id)->select('id')))
            ->orderByRaw("status = 'open' desc")->latest('id')->limit(100)->get()
            ->map(fn ($c) => $this->row($c) + ['product' => ['id' => $c->product?->id, 'name' => $c->product?->name], 'customer' => explode(' ', trim((string) $c->customer?->name))[0] ?: 'Customer'])->values();
    }

    /** open → approved (with how it will be put right) or rejected (with why); approved → resolved. */
    public function decide(Request $request, int $id)
    {
        $user = $request->user();
        $claim = WarrantyClaim::with('product')->findOrFail($id);
        $mine = $user->hasRole('platform-admin') || ($claim->product?->seller_id && $user->company_id === $claim->product->seller_id && $user->can('products.update'));
        abort_unless($mine, 404);

        $data = $request->validate(['status' => 'required|in:approved,rejected,resolved', 'resolution' => 'nullable|in:repair,replace,refund', 'note' => 'nullable|string|max:300']);

        if ($data['status'] === WarrantyClaim::RESOLVED) {
            if ($claim->status !== WarrantyClaim::APPROVED) {
                throw ValidationException::withMessages(['status' => ['Only an approved claim can be marked resolved.']]);
            }
            $claim->update(['status' => WarrantyClaim::RESOLVED, 'resolved_at' => now()]);
        } else {
            if ($claim->status !== WarrantyClaim::OPEN) {
                throw ValidationException::withMessages(['status' => ['This claim was already decided.']]);
            }
            if ($data['status'] === WarrantyClaim::APPROVED && empty($data['resolution'])) {
                throw ValidationException::withMessages(['resolution' => ['Choose how it will be put right: repair, replace or refund.']]);
            }
            if ($data['status'] === WarrantyClaim::REJECTED && ! filled($data['note'] ?? null)) {
                throw ValidationException::withMessages(['note' => ['Tell the customer why the claim is declined.']]);
            }
            $claim->update(['status' => $data['status'], 'resolution' => $data['status'] === WarrantyClaim::APPROVED ? $data['resolution'] : null,
                'decision_note' => isset($data['note']) ? trim(strip_tags($data['note'])) : null, 'decided_by' => $user->id, 'decided_at' => now()]);
        }

        $claim->customer?->notify(new SimpleNotice(['approved' => 'Your warranty claim was approved', 'rejected' => 'Your warranty claim was declined', 'resolved' => 'Your warranty claim was resolved'][$claim->status], ($claim->product?->name ?? 'Product').($claim->resolution ? ' ('.$claim->resolution.')' : '').($claim->decision_note ? ': '.$claim->decision_note : ''), '/dashboard/warranties'));
        Audit::record('warranty_claim.'.$claim->status, $claim, ['product_id' => $claim->product_id], $user);

        return $this->row($claim->fresh());
    }

    /** @return array<string, mixed> */
    public function row(WarrantyClaim $c): array
    {
        return ['id' => $c->id, 'warranty_id' => $c->warranty_id, 'description' => $c->description, 'status' => $c->status, 'resolution' => $c->resolution, 'note' => $c->decision_note,
            'created_at' => $c->created_at?->toDateString(), 'decided_at' => $c->decided_at?->toDateString(), 'resolved_at' => $c->resolved_at?->toDateString()];
    }
}
