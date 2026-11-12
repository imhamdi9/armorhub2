<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InstallationDispute;
use App\Models\InstallationOffer;
use App\Models\InstallationProof;
use App\Models\InstallationRequest;
use App\Models\InstallationViolation;
use App\Services\InstallationJobs;
use App\Services\InstallationNotifier;
use App\Services\InstallationPresenter;
use App\Services\Reputation;
use App\Support\ContactGuard;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** The customer's money-and-proof steps: accept, pay, confirm, rate, dispute, add evidence, loyalty, and the private photo files. */
class InstallationFlowController extends Controller
{
    public function __construct(private InstallationJobs $jobs, private InstallationPresenter $present, private Reputation $reputation)
    {
    }

    private function mine(Request $request, int $id): InstallationRequest
    {
        $this->jobs->releaseDue(); // no scheduler on a free host: due releases happen as people look

        return InstallationRequest::where('customer_id', $request->user()->id)->findOrFail($id);
    }

    public function accept(Request $request, int $id)
    {
        $r = $this->mine($request, $id);
        $data = $request->validate(['offer_id' => 'required|integer', 'accept_terms' => 'required|boolean', 'use_points' => 'nullable|integer|min:0']);
        $offer = InstallationOffer::where('request_id', $r->id)->findOrFail($data['offer_id']);

        return $this->present->forCustomer($this->jobs->accept($r, $offer, $request->user(), (bool) $data['accept_terms'], (int) ($data['use_points'] ?? 0)));
    }

    public function pay(Request $request, int $id)
    {
        $r = $this->mine($request, $id);
        $request->validate(['payment_method' => 'nullable|string|max:40']);
        try {
            $result = $this->jobs->pay($r, $request->input('payment_method'));
        } catch (\App\Services\Payment\PaymentUnavailable $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        return response()->json($result + ['request' => $this->present->forCustomer($r->fresh())], $result['status'] === 'redirect' ? 202 : 200);
    }

    public function confirm(Request $request, int $id)
    {
        return $this->present->forCustomer($this->jobs->confirm($this->mine($request, $id), $request->user()));
    }

    public function review(Request $request, int $id)
    {
        $r = $this->mine($request, $id);
        $data = $request->validate(['rating' => 'required|integer|between:1,5', 'comment' => 'nullable|string|max:500']);
        if (ContactGuard::contains($data['comment'] ?? null)) {
            throw ValidationException::withMessages(['comment' => ['Please do not put contact details in a review.']]);
        }
        $this->jobs->review($r, $request->user(), (int) $data['rating'], $data['comment'] ?? null);

        return $this->present->forCustomer($r->fresh());
    }

    public function dispute(Request $request, int $id)
    {
        $r = $this->mine($request, $id);
        $data = $request->validate(['type' => 'required|in:not_done,defect,leak,maintenance,other', 'description' => 'required|string|min:10|max:2000']);
        $this->jobs->openDispute($r, $request->user(), 'customer', $data['type'], $data['description']);

        return $this->present->forCustomer($r->fresh());
    }

    /** Photos or notes from the customer as evidence in a dispute. */
    public function evidence(Request $request, int $id)
    {
        $r = $this->mine($request, $id);

        return $this->addEvidence($request, $r, 'customer', fn () => $this->present->forCustomer($r->fresh()));
    }

    public function reportContact(Request $request, int $id)
    {
        $r = $this->mine($request, $id);
        $data = $request->validate(['note' => 'nullable|string|max:500']);
        abort_unless($r->selected_offer_id, 422, 'Nothing to report yet.');
        $centre = $this->jobs->centreOf($r);
        InstallationViolation::firstOrCreate(['request_id' => $r->id, 'partner_id' => $centre->id, 'reported_by' => $request->user()->id], ['note' => $data['note'] ?? null]);
        app(InstallationNotifier::class)->admins('Off-platform contact reported', "Job #{$r->id}: the customer says the centre tried to deal outside the platform.", '/dashboard/admin/disputes');

        return response()->json(['message' => 'Thank you. The platform will review this.']);
    }

    public function loyalty(Request $request)
    {
        $uid = $request->user()->id;

        return [
            'points' => $this->reputation->points($uid), 'point_value' => $this->reputation->pointValue(),
            'history' => \App\Models\LoyaltyEntry::where('user_id', $uid)->latest('id')->limit(30)->get(['points', 'reason', 'request_id', 'created_at']),
        ];
    }

    /** Shared by customers and centres. */
    public function addEvidence(Request $request, InstallationRequest $r, string $side, \Closure $respond)
    {
        $request->validate(['note' => 'nullable|string|max:300', 'photos' => 'nullable|array|max:6', 'photos.*' => 'file|mimes:jpg,jpeg,png|max:6144']);
        abort_unless(in_array($r->status, ['disputed', 'paid', 'in_progress', 'proof_submitted'], true), 422, 'Evidence can only be added while the payment is held.');
        $any = false;
        foreach ((array) $request->file('photos', []) as $file) {
            $this->jobs->storeProof($r, $request->user(), $side, 'evidence', $file, ['note' => $request->input('note')]);
            $any = true;
        }
        if (! $any && $request->filled('note')) {
            InstallationProof::create(['request_id' => $r->id, 'user_id' => $request->user()->id, 'side' => $side, 'kind' => 'evidence', 'text' => $request->input('note')]);
        }

        return $respond();
    }

    /** A proof photo, only for the two sides of the job and platform admins. Never public. */
    public function file(Request $request, int $id)
    {
        $proof = InstallationProof::findOrFail($id);
        $r = InstallationRequest::findOrFail($proof->request_id);
        $user = $request->user();
        $isCentre = $r->selected_offer_id && $user->partner_id && InstallationOffer::whereKey($r->selected_offer_id)->value('partner_id') === $user->partner_id;
        abort_unless($proof->path && ($user->hasRole('platform-admin') || $r->customer_id === $user->id || $isCentre), 404);

        return response()->file(\Illuminate\Support\Facades\Storage::disk('local')->path($proof->path), ['Cache-Control' => 'private, max-age=300', 'X-Content-Type-Options' => 'nosniff']);
    }
}
