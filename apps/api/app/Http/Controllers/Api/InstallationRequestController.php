<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InstallationRequest;
use App\Models\Order;
use App\Models\Product;
use App\Services\InstallationMatching;
use App\Support\ContactGuard;
use App\Support\Geo;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** The customer's side of installation help. Nothing about a centre beyond an alias, its record and a rounded distance is shown here. */
class InstallationRequestController extends Controller
{
    public function __construct(private InstallationMatching $matching, private \App\Services\InstallationPresenter $presenter, private \App\Services\InstallationJobs $jobs)
    {
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'subject' => 'required|string|max:160',
            'parts' => 'required|array|min:1|max:10',
            'parts.*' => 'required|string|max:80',
            'work_type' => 'nullable|in:install,repair,maintenance',
            'notes' => 'nullable|string|max:500',
            // The position comes from the phone or browser after the customer allows it: typing a place is not accepted.
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'location_consent' => 'required|accepted',
            'product_id' => 'nullable|integer|exists:products,id',
            'industry_id' => 'nullable|integer',
            'order_id' => 'nullable|integer',
        ]);

        foreach ([$data['subject'], $data['notes'] ?? '', ...$data['parts']] as $text) {
            if (ContactGuard::contains($text)) {
                throw ValidationException::withMessages(['notes' => ['Please do not put phone numbers, links or contact details here. They are shared safely after you accept an offer.']]);
            }
        }

        if (! empty($data['order_id'])) {
            $order = Order::withoutGlobalScopes()->find($data['order_id']);
            abort_unless($order && $order->customer_id === $user->id, 403);
        }

        $hours = (int) app(\App\Support\Settings\Settings::class)->get('installation.offer_hours');
        $product = ! empty($data['product_id']) ? Product::find($data['product_id']) : null;

        // The kind of job decides which centres are asked: the product's industry, or the one the customer picked among their market's.
        $allowed = $user->industryIds();
        $industry = $product?->industry_id ?? ($data['industry_id'] ?? null);
        if ($industry === null && count($allowed) === 1) {
            $industry = $allowed[0];
        }
        if ($industry === null && count($allowed) > 1) {
            throw ValidationException::withMessages(['industry_id' => ['Choose what kind of job this is.']]);
        }
        if ($industry !== null && $allowed !== [] && ! in_array((int) $industry, $allowed, true)) {
            throw ValidationException::withMessages(['industry_id' => ['This market does not run that industry.']]);
        }

        $req = InstallationRequest::create([
            'company_id' => $user->company_id, 'customer_id' => $user->id, 'order_id' => $data['order_id'] ?? null, 'product_id' => $product?->id, 'industry_id' => $industry,
            'subject' => $data['subject'], 'parts' => array_values($data['parts']), 'work_type' => $data['work_type'] ?? 'install', 'notes' => $data['notes'] ?? null,
            'latitude' => $data['latitude'], 'longitude' => $data['longitude'], 'location_consent' => true,
            'city' => $user->city, 'country' => $user->country, 'status' => 'open', 'expires_at' => now()->addHours($hours),
            'market' => ($market = app(\App\Support\Markets\Markets::class)->forCompany(\App\Models\Company::withoutGlobalScopes()->find($user->company_id)))?->code,
            'currency' => $market?->currency() ?? (string) config('platform.currency'),
        ]);

        // Tell the centres that may answer (and remember who was told: that is how "answers how often" is measured).
        foreach ($this->matching->eligiblePartners($req) as $centre) {
            \App\Models\InstallationInvite::firstOrCreate(['request_id' => $req->id, 'partner_id' => $centre->id]);
            app(\App\Services\InstallationNotifier::class)->centre($centre->id, 'New installation request near you', "{$req->subject}: ".implode(', ', $req->parts), '/dashboard/centre-requests');
        }

        return response()->json($this->present($req), 201);
    }

    public function index(Request $request)
    {
        $this->jobs->releaseDue();
        $this->jobs->expireOld();

        return InstallationRequest::where('customer_id', $request->user()->id)->latest()->get()->map(fn ($r) => $this->present($r))->values();
    }

    public function show(Request $request, int $id)
    {
        $this->jobs->releaseDue();
        $req = InstallationRequest::where('customer_id', $request->user()->id)->findOrFail($id);

        return $this->present($req, withOffers: true);
    }

    public function cancel(Request $request, int $id)
    {
        $req = InstallationRequest::where('customer_id', $request->user()->id)->findOrFail($id);
        abort_unless(in_array($req->status, ['open', 'offers_received', 'selected', 'awaiting_payment'], true), 422, 'This request can no longer be cancelled.');
        $req->update(['status' => 'cancelled']);
        $req->offers()->whereIn('status', ['submitted', 'selected'])->update(['status' => 'rejected']);

        return $this->present($req->fresh());
    }

    private function present(InstallationRequest $r, bool $withOffers = false): array
    {
        $out = $this->presenter->forCustomer($r) + ['offer_count' => $r->offers()->where('status', 'submitted')->count()];

        if ($withOffers) {
            $offers = $this->matching->rank($r, $r->offers()->whereIn('status', ['submitted', 'selected'])->with('partner')->get());
            $closest = $offers->min(fn ($o) => $o->distance_km);
            $quickest = $offers->min('eta_days');
            $best = $offers->max(fn ($o) => (float) $o->partner->rating_avg);

            $out['offers'] = $offers->values()->map(fn ($o, $i) => [
                'id' => $o->id, 'rank' => $i + 1, 'centre' => $this->alias($o->partner->id), 'tier' => $o->partner->tier, 'selected' => $o->status === 'selected',
                'rating' => $o->partner->rating_count >= 3 ? (float) $o->partner->rating_avg : null, 'rating_count' => (int) $o->partner->rating_count,
                'completed_jobs' => (int) $o->partner->completed_jobs, 'avg_response_minutes' => $o->partner->avg_response_minutes,
                'distance_km' => Geo::fuzzy((float) $o->distance_km), 'same_city' => $o->same_city,
                'price' => (float) $o->price, 'eta_days' => $o->eta_days, 'note' => $o->note,
                'badges' => array_values(array_filter([
                    $o->distance_km == $closest ? 'closest' : null,
                    $o->eta_days == $quickest ? 'fastest' : null,
                    $o->partner->rating_count >= 3 && (float) $o->partner->rating_avg == $best && $best > 0 ? 'best_rated' : null,
                ])),
            ])->all();
        }

        return $out;
    }

    /** A stable, meaningless code for a centre ("C-4F2A"): enough to compare offers, nothing that identifies the business. */
    public static function alias(int $partnerId): string
    {
        return 'C-'.str_pad(strtoupper(base_convert((string) (($partnerId * 7919 + 1013) % 1679616), 10, 36)), 4, '0', STR_PAD_LEFT);
    }
}
