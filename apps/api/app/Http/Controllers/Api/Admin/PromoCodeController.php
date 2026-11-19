<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PromoCode;
use App\Services\PromoCodes;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Discount codes: platform administrators create them and see how much each was used. */
class PromoCodeController extends Controller
{
    public function index()
    {
        $used = DB::table('promo_redemptions')->where('status', 'reserved')->selectRaw('promo_code_id, count(*) as n, sum(amount) as total')->groupBy('promo_code_id')->get()->keyBy('promo_code_id');

        return PromoCode::latest('id')->limit(200)->get()->map(fn ($p) => $this->row($p, $used[$p->id] ?? null))->values();
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $promo = PromoCode::create($data);
        Audit::record('promo.created', $promo, ['code' => $promo->code], $request->user());

        return response()->json($this->row($promo, null), 201);
    }

    public function update(Request $request, int $id)
    {
        $promo = PromoCode::findOrFail($id);
        $promo->update($this->validated($request, $promo));
        Audit::record('promo.updated', $promo, ['active' => $promo->is_active], $request->user());

        return $this->row($promo->fresh(), null);
    }

    private function validated(Request $request, ?PromoCode $promo = null): array
    {
        $s = $promo ? 'sometimes|' : '';
        if ($request->has('code')) {
            $request->merge(['code' => PromoCodes::normalise((string) $request->input('code'))]);
        }
        $data = $request->validate([
            'code' => [...($promo ? ['sometimes'] : []), 'required', 'string', 'min:3', 'max:40', Rule::unique('promo_codes', 'code')->ignore($promo?->id)],
            'type' => $s.'required|in:percent,fixed',
            'value' => $s.'required|numeric|gt:0|max:1000000',
            'max_discount' => 'nullable|numeric|gt:0',
            'min_order' => 'nullable|numeric|min:0',
            'market' => 'nullable|string|size:2|alpha',
            'starts_at' => 'nullable|date', 'ends_at' => 'nullable|date|after:starts_at',
            'usage_limit' => 'nullable|integer|min:1', 'per_customer_limit' => 'sometimes|integer|min:1|max:100',
            'is_active' => 'sometimes|boolean',
        ]);
        $type = $data['type'] ?? $promo?->type;
        if ($type === 'percent' && isset($data['value']) && (float) $data['value'] > 100) {
            throw \Illuminate\Validation\ValidationException::withMessages(['value' => ['A percentage cannot be more than 100.']]);
        }
        if (! empty($data['market'])) {
            $data['market'] = strtoupper($data['market']);
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function row(PromoCode $p, ?object $used): array
    {
        return ['id' => $p->id, 'code' => $p->code, 'type' => $p->type, 'value' => (float) $p->value, 'max_discount' => $p->max_discount !== null ? (float) $p->max_discount : null, 'min_order' => $p->min_order !== null ? (float) $p->min_order : null,
            'market' => $p->market, 'starts_at' => $p->starts_at?->toIso8601String(), 'ends_at' => $p->ends_at?->toIso8601String(), 'usage_limit' => $p->usage_limit, 'per_customer_limit' => $p->per_customer_limit,
            'seller_id' => $p->company_id, 'is_active' => $p->is_active, 'used' => (int) ($used->n ?? 0), 'given' => (float) ($used->total ?? 0)];
    }
}
