<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\PayoutAccount;
use App\Notifications\SimpleNotice;
use App\Services\InstallationNotifier;
use App\Services\SellerPayoutService;
use App\Support\Audit;
use App\Support\Settings\Settings;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** A seller says where the money goes, and asks for it when enough is available. */
class SellerPayoutController extends Controller
{
    private function seller(Request $request): Company
    {
        $user = $request->user();
        abort_unless($user->hasRole('seller') && $user->company_id, 403);

        return Company::withoutGlobalScopes()->findOrFail($user->company_id);
    }

    public function account(Request $request, Settings $settings)
    {
        $c = $this->seller($request);

        return ['account' => PayoutAccount::where('company_id', $c->id)->first()?->forSeller(), 'min_amount' => (float) $settings->get('payouts.min_amount')];
    }

    /** Saving new details sends them for checking again: money never goes to an unchecked account. */
    public function saveAccount(Request $request)
    {
        $c = $this->seller($request);
        $d = $request->validate([
            'method' => 'required|in:bank,wallet', 'holder' => 'required|string|min:3|max:120',
            'bank_name' => 'required_if:method,bank|nullable|string|max:120',
            'account_number' => ['required_if:method,bank', 'nullable', 'string', 'regex:/^[A-Za-z0-9 \-]{8,45}$/'],
            'wallet_phone' => ['required_if:method,wallet', 'nullable', 'string', 'regex:/^\+?[0-9 \-]{8,16}$/'],
        ]);

        $account = PayoutAccount::updateOrCreate(['company_id' => $c->id], [
            'method' => $d['method'], 'holder' => trim(strip_tags($d['holder'])),
            'bank_name' => $d['method'] === 'bank' ? trim(strip_tags((string) $d['bank_name'])) : null,
            'account_number' => $d['method'] === 'bank' ? preg_replace('/[ \-]/', '', $d['account_number']) : null,
            'wallet_phone' => $d['method'] === 'wallet' ? preg_replace('/[ \-]/', '', $d['wallet_phone']) : null,
            'status' => 'pending', 'note' => null, 'verified_by' => null, 'verified_at' => null,
        ]);
        Audit::record('payout_account.saved', $account, ['company_id' => $c->id]);
        app(InstallationNotifier::class)->admins('Payout account to verify', ':name sent payout details.', '/dashboard/admin/payouts', ['name' => (string) $c->name]);

        return response()->json(['account' => $account->forSeller()]);
    }

    /** Everything available, in one payout, if the account is verified and the amount reaches the minimum. */
    public function request(Request $request, SellerPayoutService $service, Settings $settings)
    {
        $c = $this->seller($request);
        $currency = strtoupper($request->validate(['currency' => 'required|string|size:3'])['currency']);
        $account = PayoutAccount::where('company_id', $c->id)->first();

        if (! $account || $account->status !== 'verified') {
            throw ValidationException::withMessages(['account' => [$account ? 'Your payout account is not verified yet.' : 'Add the account we should pay you into first.']]);
        }
        $available = (float) ($service->balances($c->id)[$currency]['available'] ?? 0);
        $min = (float) $settings->get('payouts.min_amount');
        if ($available < $min) {
            throw ValidationException::withMessages(['amount' => [__('The smallest payout is :n. You have :have available.', ['n' => number_format($min, 2), 'have' => number_format(max(0, $available), 2)])]]);
        }

        $payout = $service->create($c, $currency, $request->user(), 'seller');
        app(InstallationNotifier::class)->admins('Payout requested', ':name asked for :amount :currency.', '/dashboard/admin/payouts', ['name' => (string) $c->name, 'amount' => (string) $payout->amount, 'currency' => (string) $currency]);

        return response()->json($payout, 201);
    }
}
