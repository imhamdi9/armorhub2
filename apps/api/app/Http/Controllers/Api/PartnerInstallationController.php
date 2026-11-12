<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InstallationOffer;
use App\Models\InstallationRequest;
use App\Models\Partner;
use App\Services\InstallationMatching;
use App\Support\ContactGuard;
use App\Support\Geo;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** A centre's side: it sees WHAT must be done and roughly how far away, never who the customer is or where they live. */
class PartnerInstallationController extends Controller
{
    public function __construct(private InstallationMatching $matching)
    {
    }

    private function partner(Request $request): Partner
    {
        abort_unless($request->user()->partner_id, 422, 'Your account is not linked to a partner yet.');

        return Partner::withoutGlobalScopes()->findOrFail($request->user()->partner_id);
    }

    public function index(Request $request)
    {
        $partner = $this->partner($request);
        $open = InstallationRequest::whereIn('status', ['open', 'offers_received'])->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->latest()->limit(200)->get();
        $mine = InstallationOffer::where('partner_id', $partner->id)->whereIn('request_id', $open->pluck('id'))->get()->keyBy('request_id');

        return $open->filter(fn ($r) => $this->matching->eligible($r, $partner))->values()->map(function ($r) use ($partner, $mine) {
            $km = Geo::km($r->latitude, $r->longitude, (float) $partner->latitude, (float) $partner->longitude);

            return [
                'id' => $r->id, 'subject' => $r->subject, 'parts' => $r->parts, 'work_type' => $r->work_type, 'notes' => $r->notes,
                'area' => $r->city, 'distance_km' => Geo::fuzzy($km), 'created_at' => $r->created_at?->toIso8601String(),
                'my_offer' => ($o = $mine->get($r->id)) ? ['price' => (float) $o->price, 'eta_days' => $o->eta_days, 'note' => $o->note, 'status' => $o->status] : null,
            ];
        });
    }

    public function offer(Request $request, int $id)
    {
        $partner = $this->partner($request);
        $req = InstallationRequest::findOrFail($id);
        abort_unless($req->isOpen() && $this->matching->eligible($req, $partner), 404);

        $data = $request->validate(['price' => 'required|numeric|min:1|max:10000000', 'eta_days' => 'required|integer|min:0|max:60', 'note' => 'nullable|string|max:300']);

        if (ContactGuard::contains($data['note'] ?? null)) {
            throw ValidationException::withMessages(['note' => ['Please do not put phone numbers, links or contact details in an offer. They are shared after the customer accepts and pays.']]);
        }

        $km = Geo::km($req->latitude, $req->longitude, (float) $partner->latitude, (float) $partner->longitude);
        $offer = InstallationOffer::updateOrCreate(['request_id' => $req->id, 'partner_id' => $partner->id], [
            'price' => $data['price'], 'eta_days' => $data['eta_days'], 'note' => $data['note'] ?? null, 'status' => 'submitted', 'distance_km' => $km,
        ]);
        $req->update(['status' => 'offers_received']);
        app(\App\Services\Reputation::class)->refresh($partner);
        app(\App\Services\InstallationNotifier::class)->user($req->customer_id, 'You received an offer', 'A centre sent an offer for your installation request.', "/dashboard/installations/{$req->id}");

        return response()->json(['id' => $offer->id, 'price' => (float) $offer->price, 'eta_days' => $offer->eta_days, 'note' => $offer->note, 'status' => $offer->status], 201);
    }
}
