<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ProductApproval;
use App\Support\MarketScope;
use Illuminate\Http\Request;

/** The catalogue team: looks at the products sellers submit and approves them or sends them back with a reason. Limited to the reviewer's own market. */
class ProductReviewController extends Controller
{
    public function __construct()
    {
        Product::$rawNames = true;        // the team reads both copies side by side
    }

    public function index(Request $request)
    {
        $status = in_array($request->query('status'), ['pending', 'approved', 'rejected', 'draft'], true) ? $request->query('status') : 'pending';
        $ids = MarketScope::companyIds($request->user());

        return Product::withoutGlobalScopes()->where('review_status', $status)->whereNotNull('seller_id')
            ->when($ids !== null, fn ($q) => $q->whereIn('seller_id', $ids))
            ->with('seller:id,name')->orderBy('submitted_at')->limit(100)->get()->map(fn ($p) => $this->row($p))->values();
    }

    public function approve(Request $request, int $id, ProductApproval $approval)
    {
        return $this->row($approval->approve($this->find($request, $id), $request->user()));
    }

    public function reject(Request $request, int $id, ProductApproval $approval)
    {
        $data = $request->validate(['note' => 'required|string|min:5|max:300']);

        return $this->row($approval->reject($this->find($request, $id), $request->user(), trim(strip_tags($data['note']))));
    }

    private function find(Request $request, int $id): Product
    {
        $ids = MarketScope::companyIds($request->user());

        return Product::withoutGlobalScopes()->whereNotNull('seller_id')->when($ids !== null, fn ($q) => $q->whereIn('seller_id', $ids))->findOrFail($id);
    }

    /** @return array<string, mixed> everything the team needs to judge it, in both languages */
    private function row(Product $p): array
    {
        $p->loadMissing('seller:id,name', 'bundleItems.product:id,name,name_ar');

        return ['id' => $p->id, 'seller' => $p->seller?->name, 'status' => $p->review_status, 'note' => $p->review_note, 'submitted_at' => $p->submitted_at?->toIso8601String(),
            'name' => $p->name, 'name_ar' => $p->name_ar, 'description' => $p->description, 'description_ar' => $p->description_ar, 'category' => $p->category, 'brand' => $p->brand,
            'price' => (float) $p->base_price, 'highlights' => array_values($p->attributes['highlights'] ?? []), 'highlights_ar' => array_values($p->attributes['highlights_ar'] ?? []), 'images' => array_values((array) $p->images), 'fits_note' => $p->attributes['fits_note'] ?? null,
            'bundle' => $p->bundleItems->map(fn ($i) => ['name' => $i->product?->name, 'quantity' => (int) $i->quantity])->values()];
    }
}
