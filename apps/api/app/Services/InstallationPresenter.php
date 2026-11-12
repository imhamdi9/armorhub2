<?php

namespace App\Services;

use App\Models\InstallationRequest;
use App\Models\InstallationReview;
use App\Models\Partner;
use App\Models\CentreWalletEntry;
use App\Support\Settings\Settings;

/** What each side of an installation job may see. The rule: nothing identifying before payment, and never a real phone number. */
class InstallationPresenter
{
    public function __construct(private Settings $settings, private InstallationJobs $jobs)
    {
    }

    public static function mapsUrl(float $lat, float $lng): string
    {
        return 'https://www.google.com/maps/search/?api=1&query='.$lat.','.$lng;
    }

    private function call(InstallationRequest $r): ?array
    {
        if (! $r->proxy_extension || ($r->proxy_expires_at && $r->proxy_expires_at->isPast())) {
            return null;
        }

        return ['number' => (string) $this->settings->get('installation.proxy_number'), 'extension' => $r->proxy_extension, 'expires_at' => $r->proxy_expires_at?->toIso8601String()];
    }

    private function proofs(InstallationRequest $r): array
    {
        return $r->proofs()->orderBy('id')->get()->map(fn ($p) => [
            'id' => $p->id, 'side' => $p->side, 'kind' => $p->kind, 'text' => $p->text, 'taken_at' => $p->taken_at?->toIso8601String(), 'flagged' => (bool) $p->flagged,
            'distance_km' => $p->distance_km, 'url' => $p->path ? "/api/installation-files/{$p->id}" : null,
        ])->all();
    }

    private function dispute(InstallationRequest $r): ?array
    {
        $d = $r->disputes()->latest('id')->first();

        return $d ? ['id' => $d->id, 'side' => $d->side, 'type' => $d->type, 'description' => $d->description, 'status' => $d->status, 'decision' => $d->decision,
            'refund_amount' => $d->refund_amount !== null ? (float) $d->refund_amount : null, 'decision_note' => $d->decision_note, 'created_at' => $d->created_at?->toIso8601String()] : null;
    }

    /** Fields shared by both sides after the job has been paid for. */
    private function common(InstallationRequest $r): array
    {
        return [
            'id' => $r->id, 'subject' => $r->subject, 'parts' => $r->parts, 'work_type' => $r->work_type, 'notes' => $r->notes, 'status' => $r->status, 'currency' => $r->currency,
            'created_at' => $r->created_at?->toIso8601String(), 'objection_deadline' => $r->objection_deadline?->toIso8601String(), 'released_at' => $r->released_at?->toIso8601String(),
            'warranty_ends_at' => $r->warranty_ends_at?->toIso8601String(), 'terms' => InstallationJobs::terms(), 'proofs' => $this->proofs($r), 'dispute' => $this->dispute($r),
        ];
    }

    public function forCustomer(InstallationRequest $r): array
    {
        $out = $this->common($r) + [
            'price' => $r->price !== null ? (float) $r->price : null, 'discount' => $r->discount_amount !== null ? (float) $r->discount_amount : null,
            'pay_amount' => $r->pay_amount !== null ? (float) $r->pay_amount : null, 'points_used' => (int) $r->points_used,
            'expires_at' => $r->expires_at?->toIso8601String(),
            'refund' => $r->refund_amount !== null ? ['amount' => (float) $r->refund_amount, 'status' => $r->refund_status, 'reference' => $r->refund_reference] : null,
            'can_confirm' => $r->status === 'proof_submitted',
            'can_dispute' => in_array($r->status, ['paid', 'in_progress'], true) || ($r->status === 'proof_submitted' && $r->objection_deadline?->isFuture()),
            'can_cancel' => in_array($r->status, ['open', 'offers_received', 'selected', 'awaiting_payment'], true),
            'review' => ($rv = InstallationReview::where('request_id', $r->id)->first()) ? ['rating' => $rv->rating, 'comment' => $rv->comment] : null,
            'can_review' => $r->status === 'released' && ! InstallationReview::where('request_id', $r->id)->exists(),
        ];

        if ($r->selected_offer_id) {
            $centre = $this->jobs->centreOf($r);
            $out['centre'] = ['code' => \App\Http\Controllers\Api\InstallationRequestController::alias($centre->id), 'tier' => $centre->tier,
                'rating' => $centre->rating_count >= 3 ? (float) $centre->rating_avg : null, 'rating_count' => (int) $centre->rating_count, 'completed_jobs' => (int) $centre->completed_jobs];

            // After payment the customer gets the centre's details, the Google Maps place, and a substitute number instead of the phone.
            if ($r->paid_at) {
                $out['centre'] += ['name' => $centre->name, 'address' => $centre->address, 'city' => $centre->city,
                    'maps_url' => $centre->latitude !== null ? self::mapsUrl((float) $centre->latitude, (float) $centre->longitude) : null, 'call' => $this->call($r)];
            }
        }

        return $out;
    }

    public function forCentre(InstallationRequest $r): array
    {
        $entry = CentreWalletEntry::where('request_id', $r->id)->where('amount', '>', 0)->latest('id')->first();
        $out = $this->common($r) + [
            'price' => (float) $r->price, 'fee_percent' => (float) $r->fee_percent, 'fee_amount' => (float) $r->fee_amount, 'you_earn' => (float) $r->centre_amount,
            'money' => $entry?->status, 'area' => $r->city,
            'can_start' => $r->status === 'paid', 'can_add_proof' => in_array($r->status, ['paid', 'in_progress', 'proof_submitted'], true),
            'can_dispute' => in_array($r->status, ['paid', 'in_progress', 'proof_submitted'], true),
        ];

        if ($r->paid_at) {
            $customer = $r->customer;
            $out['customer'] = ['name' => $customer?->name, 'city' => $r->city, 'maps_url' => self::mapsUrl($r->latitude, $r->longitude), 'call' => $this->call($r)];
        }

        return $out;
    }
}
