<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PromoCode;
use App\Services\PromoCodes;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** A seller's own discount codes: they apply only to that seller's products, and the seller pays for what they give. */
class SellerPromoController extends Controller
{
    private const MAX_PERCENT = 90;
    private const MAX_CODES = 100;

    private function companyId(Request $request): int
    {
        abort_unless($request->user()->hasRole('seller') && $request->user()->company_id, 403);

        return (int) $request->user()->company_id;
    }

    public function index(Request $request)
    {
        $cid = $this->companyId($request);
        $used = DB::table('promo_redemptions')->where('status', 'reserved')->selectRaw('promo_code_id, count(*) as n, sum(amount) as total')->groupBy('promo_code_id')->get()->keyBy('promo_code_id');

        return PromoCode::where('company_id', $cid)->latest('id')->get()->map(fn ($p) => $this->row($p, $used[$p->id] ?? null))->values();
    }

    public function store(Request $request)
    {
        $cid = $this->companyId($request);
        if (PromoCode::where('company_id', $cid)->count() >= self::MAX_CODES) {
            throw ValidationException::withMessages(['code' => [__('You reached the limit of :n codes. Switch some off or ask support.', ['n' => self::MAX_CODES])]]);
        }
        $data = $this->validated($request) + ['company_id' => $cid, 'is_active' => true];
        $promo = PromoCode::create($data);
        Audit::record('promo.created_by_seller', $promo, ['code' => $promo->code], $request->user());

        return response()->json($this->row($promo, null), 201);
    }

    public function update(Request $request, int $id)
    {
        $promo = PromoCode::where('company_id', $this->companyId($request))->findOrFail($id);
        $promo->update($this->validated($request, $promo));
        Audit::record('promo.updated_by_seller', $promo, ['active' => $promo->is_active], $request->user());

        return $this->row($promo->fresh(), null);
    }

    private function validated(Request $request, ?PromoCode $promo = null): array
    {
        $s = $promo ? 'sometimes|' : '';
        if ($request->has('code')) {
            $request->merge(['code' => PromoCodes::normalise((string) $request->input('code'))]);
        }
        $data = $request->validate([
            // The seller chooses which of their products the code applies to (at least one); it never touches anything else.
            'product_ids' => [...($promo ? ['sometimes'] : []), 'required', 'array', 'min:1', 'max:200'], 'product_ids.*' => 'integer|distinct',
            'code' => [...($promo ? ['sometimes'] : []), 'required', 'string', 'min:3', 'max:40', Rule::unique('promo_codes', 'code')->ignore($promo?->id)],
            'type' => $s.'required|in:percent,fixed',
            'value' => $s.'required|numeric|gt:0|max:1000000',
            'max_discount' => 'nullable|numeric|gt:0',
            'min_order' => 'nullable|numeric|min:0',
            'starts_at' => 'nullable|date', 'ends_at' => 'nullable|date|after:starts_at',
            'usage_limit' => 'nullable|integer|min:1', 'per_customer_limit' => 'sometimes|integer|min:1|max:100',
            'is_active' => 'sometimes|boolean',
        ]);
        if (isset($data['product_ids'])) {
            $mine = \App\Models\Product::where('seller_id', $this->companyId($request))->whereIn('id', $data['product_ids'])->pluck('id')->all();
            if (count($mine) !== count($data['product_ids'])) {
                throw ValidationException::withMessages(['product_ids' => ['A code can only apply to your own products.']]);
            }
            $data['product_ids'] = array_map('intval', $data['product_ids']);
        }
        $type = $data['type'] ?? $promo?->type;
        if ($type === 'percent' && isset($data['value']) && (float) $data['value'] > self::MAX_PERCENT) {
            throw ValidationException::withMessages(['value' => [__('A percentage code can give at most :n%.', ['n' => self::MAX_PERCENT])]]);
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function row(PromoCode $p, ?object $used): array
    {
        return ['id' => $p->id, 'code' => $p->code, 'type' => $p->type, 'value' => (float) $p->value, 'max_discount' => $p->max_discount !== null ? (float) $p->max_discount : null,
            'min_order' => $p->min_order !== null ? (float) $p->min_order : null, 'starts_at' => $p->starts_at?->toIso8601String(), 'ends_at' => $p->ends_at?->toIso8601String(),
            'product_ids' => $p->product_ids ?? [], 'products' => \App\Models\Product::whereIn('id', $p->product_ids ?? [])->pluck('name', 'id'), 'usage_limit' => $p->usage_limit, 'per_customer_limit' => $p->per_customer_limit, 'is_active' => $p->is_active, 'used' => (int) ($used->n ?? 0), 'given' => (float) ($used->total ?? 0)];
    }
}
