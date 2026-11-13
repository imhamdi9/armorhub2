<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CentreWalletEntry;
use App\Models\CentreWithdrawal;
use App\Models\InstallationDispute;
use App\Models\InstallationOffer;
use App\Models\InstallationRequest;
use App\Models\InstallationViolation;
use App\Models\Partner;
use App\Services\InstallationJobs;
use App\Services\InstallationNotifier;
use App\Services\InstallationPresenter;
use App\Services\Reputation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Platform administration of disputes, refunds, centre withdrawals and off-platform violations. Money never moves in the app: an admin pays by bank and records the reference. */
class InstallationAdminController extends Controller
{
    public function __construct(private InstallationJobs $jobs, private InstallationPresenter $present)
    {
    }

    /** A market admin reaches only the jobs, centres and withdrawals of their own market. */
    private function mine(Request $request): ?array
    {
        return \App\Support\MarketScope::companyIds($request->user());
    }

    private function jobOf(Request $request, int $requestId): InstallationRequest
    {
        $ids = $this->mine($request);

        return InstallationRequest::withoutGlobalScopes()->when($ids !== null, fn ($q) => $q->whereIn('company_id', $ids))->findOrFail($requestId);
    }

    private function disputeRow(InstallationDispute $d): array
    {
        $r = InstallationRequest::findOrFail($d->request_id);
        $centre = $r->selected_offer_id ? $this->jobs->centreOf($r) : null;

        return [
            'id' => $d->id, 'status' => $d->status, 'side' => $d->side, 'type' => $d->type, 'description' => $d->description, 'decision' => $d->decision,
            'refund_amount' => $d->refund_amount !== null ? (float) $d->refund_amount : null, 'decision_note' => $d->decision_note, 'created_at' => $d->created_at?->toIso8601String(),
            'job' => ['id' => $r->id, 'subject' => $r->subject, 'parts' => $r->parts, 'status' => $r->status, 'price' => (float) $r->price, 'pay_amount' => (float) $r->pay_amount, 'currency' => $r->currency,
                'customer' => $r->customer?->name, 'centre' => $centre?->name, 'refund_status' => $r->refund_status, 'refund_reference' => $r->refund_reference],
            'evidence' => $r->proofs()->orderBy('id')->get()->map(fn ($p) => ['id' => $p->id, 'side' => $p->side, 'kind' => $p->kind, 'text' => $p->text, 'flagged' => (bool) $p->flagged,
                'distance_km' => $p->distance_km, 'taken_at' => $p->taken_at?->toIso8601String(), 'url' => $p->path ? "/api/installation-files/{$p->id}" : null])->all(),
        ];
    }

    public function disputes(Request $request)
    {
        $ids = $this->mine($request);

        return InstallationDispute::latest('id')->when($ids !== null, fn ($q) => $q->whereIn('request_id', InstallationRequest::withoutGlobalScopes()->whereIn('company_id', $ids)->select('id')))
            ->limit(100)->get()->map(fn ($d) => $this->disputeRow($d))->values();
    }

    public function decide(Request $request, int $id)
    {
        $d = InstallationDispute::findOrFail($id);
        $this->jobOf($request, $d->request_id);
        $data = $request->validate(['decision' => 'required|in:release,refund_full,refund_partial', 'refund_amount' => 'nullable|numeric|min:0', 'note' => 'required|string|min:5|max:1000']);
        $this->jobs->resolveDispute($d, $request->user(), $data['decision'], isset($data['refund_amount']) ? (float) $data['refund_amount'] : null, $data['note']);

        return $this->disputeRow($d->fresh());
    }

    /** The refund is sent by bank or through the provider; the admin records that it was done. */
    public function refundPaid(Request $request, int $id)
    {
        $r = $this->jobOf($request, $id);
        $data = $request->validate(['reference' => 'required|string|max:120']);
        abort_unless($r->refund_status === 'pending', 422, 'There is no pending refund for this job.');
        $r->update(['refund_status' => 'refunded', 'refund_reference' => $data['reference']]);
        app(InstallationNotifier::class)->user($r->customer_id, 'Refund sent', "{$r->refund_amount} {$r->currency} was refunded (reference {$data['reference']}).", "/dashboard/installations/{$r->id}");

        return ['id' => $r->id, 'refund_status' => 'refunded'];
    }

    public function withdrawals(Request $request)
    {
        $ids = $this->mine($request);

        return CentreWithdrawal::latest('id')->when($ids !== null, fn ($q) => $q->whereIn('company_id', $ids))->limit(100)->get()->map(fn ($w) => [
            'id' => $w->id, 'company' => \App\Models\Company::withoutGlobalScopes()->whereKey($w->company_id)->value('name'), 'amount' => (float) $w->amount, 'currency' => $w->currency,
            'method' => $w->method, 'account_name' => $w->account_name, 'account_number' => $w->account_number, 'bank_name' => $w->bank_name, 'status' => $w->status,
            'reference' => $w->reference, 'note' => $w->note, 'created_at' => $w->created_at?->toIso8601String(),
        ])->values();
    }

    public function payWithdrawal(Request $request, int $id)
    {
        $w = CentreWithdrawal::when($this->mine($request) !== null, fn ($q) => $q->whereIn('company_id', $this->mine($request)))->findOrFail($id);
        $data = $request->validate(['reference' => 'required|string|max:120']);
        abort_unless($w->status === 'requested', 422, 'Already processed.');

        DB::transaction(function () use ($w, $request, $data) {
            $w->update(['status' => 'paid', 'reference' => $data['reference'], 'processed_by' => $request->user()->id, 'processed_at' => now()]);
            CentreWalletEntry::where('withdrawal_id', $w->id)->update(['status' => 'paid']);
        });
        $this->tellCompany($w, 'Withdrawal paid', "{$w->amount} {$w->currency} was sent (reference {$data['reference']}).");

        return ['id' => $w->id, 'status' => 'paid'];
    }

    public function rejectWithdrawal(Request $request, int $id)
    {
        $w = CentreWithdrawal::when($this->mine($request) !== null, fn ($q) => $q->whereIn('company_id', $this->mine($request)))->findOrFail($id);
        $data = $request->validate(['note' => 'required|string|max:300']);
        abort_unless($w->status === 'requested', 422, 'Already processed.');

        DB::transaction(function () use ($w, $request, $data) {
            $w->update(['status' => 'rejected', 'note' => $data['note'], 'processed_by' => $request->user()->id, 'processed_at' => now()]);
            CentreWalletEntry::where('withdrawal_id', $w->id)->update(['status' => 'void']); // the money is available again
        });
        $this->tellCompany($w, 'Withdrawal not paid', $data['note']);

        return ['id' => $w->id, 'status' => 'rejected'];
    }

    private function tellCompany(CentreWithdrawal $w, string $title, string $body): void
    {
        app(InstallationNotifier::class)->user($w->requested_by, $title, $body, '/dashboard/wallet');
    }

    public function violations(Request $request)
    {
        $ids = $this->mine($request);

        return InstallationViolation::latest('id')->when($ids !== null, fn ($q) => $q->whereIn('partner_id', Partner::withoutGlobalScopes()->whereIn('company_id', $ids)->select('id')))->limit(100)->get()->map(function ($v) {
            $p = Partner::withoutGlobalScopes()->find($v->partner_id);

            return ['id' => $v->id, 'request_id' => $v->request_id, 'centre' => $p?->name, 'strikes' => (int) $p?->strikes, 'note' => $v->note, 'status' => $v->status, 'created_at' => $v->created_at?->toIso8601String()];
        })->values();
    }

    /** Confirmed: the centre gets a strike. Three strikes suspend it. */
    public function decideViolation(Request $request, int $id)
    {
        $v = InstallationViolation::findOrFail($id);
        if ($this->mine($request) !== null) {
            Partner::withoutGlobalScopes()->whereIn('company_id', $this->mine($request))->findOrFail($v->partner_id);
        }
        $data = $request->validate(['decision' => 'required|in:confirmed,dismissed']);
        abort_unless($v->status === 'open', 422, 'Already decided.');
        $v->update(['status' => $data['decision'], 'decided_by' => $request->user()->id]);

        if ($data['decision'] === 'confirmed') {
            $p = Partner::withoutGlobalScopes()->findOrFail($v->partner_id);
            $p->forceFill(['strikes' => $p->strikes + 1] + ($p->strikes + 1 >= 3 ? ['is_active' => false] : []))->save();
            app(Reputation::class)->refresh($p);
            app(InstallationNotifier::class)->centre($p->id, 'Warning: dealing outside the platform', $p->strikes >= 3 ? 'Your account was suspended after repeated off-platform dealing.' : "This is strike {$p->strikes} of 3. At 3 strikes the account is suspended.", '/dashboard/centre-jobs');
        }

        return ['id' => $v->id, 'status' => $v->status];
    }
}
