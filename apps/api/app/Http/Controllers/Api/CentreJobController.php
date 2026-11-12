<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CentreWalletEntry;
use App\Models\CentreWithdrawal;
use App\Models\InstallationOffer;
use App\Models\InstallationRequest;
use App\Services\InstallationJobs;
use App\Services\InstallationPresenter;
use App\Support\ContactGuard;
use App\Support\Settings\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** A centre's accepted jobs (details appear only after the customer pays), proof of work, and its wallet. */
class CentreJobController extends Controller
{
    public function __construct(private InstallationJobs $jobs, private InstallationPresenter $present)
    {
    }

    private function partnerId(Request $request): int
    {
        abort_unless($request->user()->partner_id, 422, 'Your account is not linked to a partner yet.');

        return (int) $request->user()->partner_id;
    }

    private function mine(Request $request, int $id): InstallationRequest
    {
        $this->jobs->releaseDue();
        $offers = InstallationOffer::where('partner_id', $this->partnerId($request))->pluck('id');

        return InstallationRequest::whereIn('selected_offer_id', $offers)->findOrFail($id);
    }

    public function index(Request $request)
    {
        $this->jobs->releaseDue();
        $offers = InstallationOffer::where('partner_id', $this->partnerId($request))->pluck('id');

        return InstallationRequest::whereIn('selected_offer_id', $offers)->latest('id')->limit(100)->get()->map(fn ($r) => $this->present->forCentre($r))->values();
    }

    public function show(Request $request, int $id)
    {
        return $this->present->forCentre($this->mine($request, $id));
    }

    public function start(Request $request, int $id)
    {
        return $this->present->forCentre($this->jobs->start($this->mine($request, $id)));
    }

    public function proof(Request $request, int $id)
    {
        $r = $this->mine($request, $id);
        $data = $request->validate([
            'before' => 'nullable|array|max:6', 'before.*' => 'file|mimes:jpg,jpeg,png|max:6144',
            'after' => 'nullable|array|max:6', 'after.*' => 'file|mimes:jpg,jpeg,png|max:6144',
            'serial' => 'nullable|string|max:120', 'note' => 'nullable|string|max:300',
            // From the phone's camera and location after the centre allows them; used to flag photos taken far from the job site.
            'latitude' => 'nullable|numeric|between:-90,90', 'longitude' => 'nullable|numeric|between:-180,180', 'taken_at' => 'nullable|date',
        ]);
        if (ContactGuard::contains($data['note'] ?? null)) {
            throw ValidationException::withMessages(['note' => ['Please do not put contact details here.']]);
        }

        return $this->present->forCentre($this->jobs->submitProof($r, $request->user(), (array) $request->file('before', []), (array) $request->file('after', []), $data));
    }

    public function dispute(Request $request, int $id)
    {
        $r = $this->mine($request, $id);
        $data = $request->validate(['type' => 'required|in:not_done,defect,leak,maintenance,other', 'description' => 'required|string|min:10|max:2000']);
        $this->jobs->openDispute($r, $request->user(), 'centre', $data['type'], $data['description']);

        return $this->present->forCentre($r->fresh());
    }

    public function evidence(Request $request, int $id)
    {
        $r = $this->mine($request, $id);

        return app(InstallationFlowController::class)->addEvidence($request, $r, 'centre', fn () => $this->present->forCentre($r->fresh()));
    }

    // ------------------------------------------------------------------ wallet

    private function companyId(Request $request): int
    {
        abort_unless($request->user()->company_id, 422, 'Your account is not linked to a company.');

        return (int) $request->user()->company_id;
    }

    /** @return array<string, array{held: float, available: float, pending_withdrawal: float, withdrawn: float}> */
    public static function balances(int $companyId): array
    {
        $out = [];
        foreach (CentreWalletEntry::where('company_id', $companyId)->get() as $e) {
            $b = &$out[$e->currency];
            $b ??= ['held' => 0.0, 'available' => 0.0, 'pending_withdrawal' => 0.0, 'withdrawn' => 0.0];
            $a = (float) $e->amount;
            match ($e->status) {
                'held' => $b['held'] += $a,
                'available' => $b['available'] += $a,
                'in_withdrawal' => [$b['available'] += $a, $b['pending_withdrawal'] -= $a],
                'paid' => [$b['available'] += $a, $b['withdrawn'] -= $a],
                default => null,
            };
            unset($b);
        }

        return array_map(fn ($b) => array_map(fn ($v) => round($v, 2), $b), $out);
    }

    public function wallet(Request $request)
    {
        $this->jobs->releaseDue();
        $company = $this->companyId($request);

        return [
            'balances' => (object) self::balances($company),
            'min_withdrawal' => (float) app(Settings::class)->get('installation.min_withdrawal'),
            'withdrawals' => CentreWithdrawal::where('company_id', $company)->latest('id')->limit(30)->get()->map(fn ($w) => [
                'id' => $w->id, 'amount' => (float) $w->amount, 'currency' => $w->currency, 'method' => $w->method, 'account_name' => $w->account_name,
                'account_last4' => substr((string) $w->account_number, -4), 'bank_name' => $w->bank_name, 'status' => $w->status, 'reference' => $w->reference, 'note' => $w->note,
                'created_at' => $w->created_at?->toIso8601String(),
            ]),
        ];
    }

    public function withdraw(Request $request)
    {
        $company = $this->companyId($request);
        $data = $request->validate([
            'amount' => 'required|numeric|min:1', 'currency' => 'required|string|size:3', 'method' => 'required|in:bank,wallet',
            'account_name' => 'required|string|max:120', 'account_number' => 'required|string|min:6|max:60', 'bank_name' => 'nullable|required_if:method,bank|string|max:120',
        ]);
        $currency = strtoupper($data['currency']);
        $min = (float) app(Settings::class)->get('installation.min_withdrawal');

        return DB::transaction(function () use ($request, $company, $data, $currency, $min) {
            // Serialise withdrawals of one company: the balance is checked against rows nobody else can change meanwhile.
            CentreWalletEntry::where('company_id', $company)->lockForUpdate()->get();
            $available = self::balances($company)[$currency]['available'] ?? 0.0;
            if ((float) $data['amount'] < $min) {
                throw ValidationException::withMessages(['amount' => ["The smallest withdrawal is {$min} {$currency}."]]);
            }
            if ((float) $data['amount'] > $available + 0.0001) {
                throw ValidationException::withMessages(['amount' => ['This is more than your available balance.']]);
            }

            $w = CentreWithdrawal::create(['company_id' => $company, 'requested_by' => $request->user()->id, 'amount' => $data['amount'], 'currency' => $currency, 'method' => $data['method'],
                'account_name' => $data['account_name'], 'account_number' => $data['account_number'], 'bank_name' => $data['bank_name'] ?? null]);
            CentreWalletEntry::create(['company_id' => $company, 'amount' => -1 * (float) $data['amount'], 'currency' => $currency, 'status' => 'in_withdrawal', 'withdrawal_id' => $w->id]);
            app(\App\Services\InstallationNotifier::class)->admins('A centre asked to withdraw money', "{$data['amount']} {$currency}", '/dashboard/admin/withdrawals');

            return response()->json(['id' => $w->id, 'status' => $w->status], 201);
        });
    }
}
