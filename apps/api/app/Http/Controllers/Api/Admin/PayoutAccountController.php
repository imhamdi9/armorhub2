<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PayoutAccount;
use App\Models\User;
use App\Notifications\SimpleNotice;
use App\Support\Audit;
use Illuminate\Http\Request;

/** Checking the accounts sellers want to be paid into. The full number is only ever shown here. */
class PayoutAccountController extends Controller
{
    public function index(Request $request)
    {
        $ids = \App\Support\MarketScope::companyIds($request->user());

        return PayoutAccount::with('company:id,name')->when($ids !== null, fn ($q) => $q->whereIn('company_id', $ids))
            ->orderByRaw("status = 'pending' desc")->latest('id')->limit(100)->get()
            ->map(fn ($a) => $a->forPayer() + ['id' => $a->id, 'company' => $a->company?->name, 'note' => $a->note])->values();
    }

    public function decide(Request $request, int $id)
    {
        $d = $request->validate(['decision' => 'required|in:verified,rejected', 'note' => 'required_if:decision,rejected|nullable|string|max:200']);
        $ids = \App\Support\MarketScope::companyIds($request->user());
        $account = PayoutAccount::when($ids !== null, fn ($q) => $q->whereIn('company_id', $ids))->findOrFail($id);
        $account->update(['status' => $d['decision'], 'note' => $d['note'] ?? null, 'verified_by' => $request->user()->id, 'verified_at' => now()]);
        Audit::record('payout_account.'.$d['decision'], $account, ['company_id' => $account->company_id], $request->user());

        User::where('company_id', $account->company_id)->role('seller')->get()->each(fn (User $u) => $u->notify(new SimpleNotice(
            $d['decision'] === 'verified' ? 'Your payout account was verified' : 'Your payout account was not accepted', $d['note'] ?? '', '/dashboard/earnings')));

        return ['id' => $account->id, 'status' => $account->status];
    }
}
