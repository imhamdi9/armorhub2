<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\SellerLedgerEntry;
use App\Models\SellerPayout;
use App\Services\SellerPayoutService;
use App\Support\Audit;
use Illuminate\Http\Request;

/** Platform admins pay sellers out: see who is owed what, prepare a payout, record that it was paid. */
class PayoutController extends Controller
{
    public function index(Request $request, SellerPayoutService $service)
    {
        $ids = \App\Support\MarketScope::companyIds($request->user());
        $sellers = Company::withoutGlobalScopes()->where('type', 'supplier')->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))->whereIn('id', SellerLedgerEntry::select('company_id'))->orderBy('name')->get();

        return [
            'sellers' => $sellers->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'balances' => (object) $service->balances($c->id)])->values(),
            'payouts' => SellerPayout::with('company:id,name')->when($ids !== null, fn ($q) => $q->whereIn('company_id', $ids))->latest('id')->limit(50)->get(),
        ];
    }

    public function store(Request $request, SellerPayoutService $service)
    {
        $data = $request->validate(['company_id' => 'required|integer', 'currency' => 'required|string|size:3']);
        $ids = \App\Support\MarketScope::companyIds($request->user());
        $seller = Company::withoutGlobalScopes()->where('type', 'supplier')->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))->findOrFail($data['company_id']);

        $payout = $service->create($seller, strtoupper($data['currency']), $request->user());
        Audit::record('payout.created', $payout, ['company_id' => $seller->id, 'amount' => $payout->amount]);

        return response()->json($payout, 201);
    }

    public function paid(Request $request, int $id, SellerPayoutService $service)
    {
        $reference = $request->validate(['reference' => 'required|string|max:120'])['reference'];
        $ids = \App\Support\MarketScope::companyIds($request->user());
        $payout = $service->markPaid(SellerPayout::when($ids !== null, fn ($q) => $q->whereIn('company_id', $ids))->findOrFail($id), $reference, $request->user());
        Audit::record('payout.paid', $payout, ['reference' => $reference]);

        return $payout;
    }
}
