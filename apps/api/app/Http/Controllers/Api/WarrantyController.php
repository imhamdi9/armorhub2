<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Warranty;
use App\Models\WarrantyClaim;
use Illuminate\Http\Request;

class WarrantyController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $claims = app(WarrantyClaimController::class);

        return Warranty::with(['product:id,name,requires_shipping', 'order:id,total_amount,currency'])
            ->when($user->hasRole('customer'), fn ($q) => $q->where('customer_id', $user->id))
            ->orderByDesc('ends_at')
            ->get()
            ->map(function (Warranty $w) use ($claims) {
                $list = WarrantyClaim::where('warranty_id', $w->id)->latest('id')->get();
                $active = $w->ends_at->isFuture();

                return $w->toArray() + [
                    'active' => $active,
                    'claims' => $list->map(fn ($c) => $claims->row($c))->values(),
                    // Goods are claimed on here; a warranted service is put right through its own job.
                    'can_claim' => $active && (bool) $w->product?->requires_shipping && ! $list->contains(fn ($c) => in_array($c->status, [WarrantyClaim::OPEN, WarrantyClaim::APPROVED], true)),
                ];
            });
    }
}
