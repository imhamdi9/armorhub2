<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InstallationBooking;
use App\Models\InstallationOffer;
use App\Models\InstallationRequest;
use App\Models\InstallationReview;
use App\Models\Partner;
use App\Services\InstallationNotifier;
use App\Support\ContactGuard;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** What a centre does for itself: a list of usual prices, answers to its reviews, and telling a customer it is on its way. */
class CentreToolsController extends Controller
{
    private function partner(Request $request): Partner
    {
        abort_unless($request->user()->partner_id, 422, 'Your account is not linked to a partner yet.');

        return Partner::withoutGlobalScopes()->findOrFail($request->user()->partner_id);
    }

    public function priceList(Request $request)
    {
        return ['items' => $this->partner($request)->price_list ?? []];
    }

    public function savePriceList(Request $request)
    {
        $data = $request->validate([
            'items' => 'present|array|max:20',
            'items.*.service' => 'required|string|min:2|max:80',
            'items.*.price' => 'required|numeric|min:0|max:10000000',
            'items.*.note' => 'nullable|string|max:120',
        ]);
        $items = [];
        foreach ($data['items'] as $row) {
            foreach (['service', 'note'] as $k) {
                if (ContactGuard::contains($row[$k] ?? null)) {
                    throw ValidationException::withMessages(['items' => ['Please do not put contact details here.']]);
                }
            }
            $items[] = ['service' => trim(strip_tags($row['service'])), 'price' => round((float) $row['price'], 2), 'note' => isset($row['note']) ? trim(strip_tags($row['note'])) : null];
        }
        $p = $this->partner($request);
        $p->update(['price_list' => $items]);
        \App\Support\Audit::record('centre.price_list_changed', $p, ['count' => count($items)], $request->user());

        return ['items' => $items];
    }

    /** What customers wrote about this centre. */
    public function reviews(Request $request)
    {
        return InstallationReview::where('partner_id', $this->partner($request)->id)->latest('id')->limit(100)->get()
            ->map(fn ($r) => ['id' => $r->id, 'rating' => (int) $r->rating, 'comment' => $r->comment, 'reply' => $r->reply, 'created_at' => $r->created_at?->toIso8601String()])->values();
    }

    public function reply(Request $request, int $id)
    {
        $review = InstallationReview::where('partner_id', $this->partner($request)->id)->findOrFail($id);
        if ($review->reply) {
            throw ValidationException::withMessages(['reply' => ['You already answered this review.']]);
        }
        $data = $request->validate(['reply' => 'required|string|min:2|max:500']);
        if (ContactGuard::contains($data['reply'])) {
            throw ValidationException::withMessages(['reply' => ['Please do not put contact details here.']]);
        }
        $review->update(['reply' => trim(strip_tags($data['reply'])), 'replied_at' => now()]);
        app(InstallationNotifier::class)->user($review->customer_id, 'The centre answered your review', ':text', "/dashboard/installations/{$review->request_id}", ['text' => mb_substr($review->reply, 0, 120)]);

        return ['id' => $review->id, 'reply' => $review->reply];
    }

    /** "We are on our way": only for a booked job, only around its time, and once. */
    public function onTheWay(Request $request, int $id)
    {
        $partner = $this->partner($request);
        $offers = InstallationOffer::where('partner_id', $partner->id)->pluck('id');
        $job = InstallationRequest::whereIn('selected_offer_id', $offers)->when(! $request->user()->hasRole('partner-manager'), fn ($q) => $q->where('technician_id', $request->user()->id))->findOrFail($id);
        $booking = InstallationBooking::where('request_id', $job->id)->where('status', 'booked')->first();
        if (! $booking || ! in_array($job->status, ['paid', 'in_progress'], true)) {
            throw ValidationException::withMessages(['booking' => ['This job has no active booking.']]);
        }
        if (now()->lt($booking->starts_at->copy()->subHours(6)) || now()->gt($booking->ends_at)) {
            throw ValidationException::withMessages(['booking' => ['You can say you are on the way only close to the booked time.']]);
        }
        if (! $booking->on_the_way_at) {
            $booking->update(['on_the_way_at' => now()]);
            app(InstallationNotifier::class)->user($job->customer_id, 'The centre is on its way', 'The centre set out for your appointment.', "/dashboard/installations/{$job->id}");
        }

        return ['on_the_way_at' => $booking->fresh()->on_the_way_at?->toIso8601String()];
    }
}
