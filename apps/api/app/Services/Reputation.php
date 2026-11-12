<?php

namespace App\Services;

use App\Models\InstallationInvite;
use App\Models\InstallationOffer;
use App\Models\InstallationRequest;
use App\Models\InstallationReview;
use App\Models\LoyaltyEntry;
use App\Models\Partner;
use App\Support\Settings\Settings;

/**
 * The rating engine for centres and the loyalty programme for customers.
 *
 * A centre's record is rebuilt from facts (reviews of paid-and-released jobs, how fast and how often it answers, lost disputes),
 * never typed in: rating, completed jobs, answer speed, answer rate, strikes and a tier (new, bronze, silver, gold).
 * Customers earn points for paid jobs and reviews; points become a discount that the platform pays out of its own fee.
 */
class Reputation
{
    public function __construct(private Settings $settings)
    {
    }

    public function refresh(Partner $partner): Partner
    {
        $reviews = InstallationReview::where('partner_id', $partner->id);
        $count = (int) $reviews->count();
        $avg = $count > 0 ? round((float) $reviews->avg('rating'), 2) : null;

        $jobs = InstallationRequest::where('status', 'released')->whereIn('selected_offer_id', InstallationOffer::where('partner_id', $partner->id)->select('id'))->count();

        // How quickly the centre answers: minutes from the request to its first offer, over its last 50 offers.
        $offers = InstallationOffer::where('partner_id', $partner->id)->latest('id')->limit(50)->get();
        $requests = InstallationRequest::whereIn('id', $offers->pluck('request_id'))->get()->keyBy('id');
        $minutes = $offers->map(fn ($o) => isset($requests[$o->request_id]) ? max(0, $requests[$o->request_id]->created_at->diffInMinutes($o->created_at)) : null)->filter(fn ($m) => $m !== null);
        $response = $minutes->isNotEmpty() ? (int) round($minutes->avg()) : null;

        // How often it answers: offers over requests it was told about in the last 30 days (needs at least 5 invitations to mean anything).
        $invited = InstallationInvite::where('partner_id', $partner->id)->where('created_at', '>=', now()->subDays(30))->pluck('request_id');
        $answered = InstallationOffer::where('partner_id', $partner->id)->whereIn('request_id', $invited)->count();
        $acceptance = $invited->count() >= 5 ? round(min(100, 100 * $answered / $invited->count()), 2) : null;

        $partner->forceFill([
            'rating_avg' => $avg, 'rating_count' => $count, 'completed_jobs' => $jobs, 'avg_response_minutes' => $response, 'acceptance_rate' => $acceptance,
            'tier' => $this->tier($partner, $jobs, $avg, $count),
        ])->save();

        return $partner;
    }

    /** suspended centres are handled by is_active; this is the earned badge. */
    public function tier(Partner $p, int $jobs, ?float $avg, int $ratings): string
    {
        $lostRate = $jobs > 0 ? $p->disputes_lost / $jobs : ($p->disputes_lost > 0 ? 1 : 0);

        return match (true) {
            $p->strikes >= 2 => 'new',
            $jobs >= 50 && $ratings >= 20 && $avg !== null && $avg >= 4.7 && $lostRate <= 0.02 => 'gold',
            $jobs >= 15 && $ratings >= 8 && $avg !== null && $avg >= 4.3 && $lostRate <= 0.05 => 'silver',
            $jobs >= 3 => 'bronze',
            default => 'new',
        };
    }

    /** Percentage points the platform takes off its fee for good centres. */
    public function feeDiscount(Partner $p): float
    {
        return match ($p->tier) {
            'gold' => (float) $this->settings->get('installation.fee_discount.gold'),
            'silver' => (float) $this->settings->get('installation.fee_discount.silver'),
            default => 0.0,
        };
    }

    // ---------------------------------------------------------------- customers' loyalty

    public function points(int $userId): int
    {
        return (int) LoyaltyEntry::where('user_id', $userId)->sum('points');
    }

    public function pointValue(): float
    {
        return (float) $this->settings->get('installation.point_value');
    }

    public function earn(int $userId, int $points, string $reason, ?int $requestId = null): void
    {
        if ($points !== 0) {
            LoyaltyEntry::create(['user_id' => $userId, 'points' => $points, 'reason' => $reason, 'request_id' => $requestId]);
        }
    }

    public function pointsForPayment(float $paid): int
    {
        return (int) floor($paid * (float) $this->settings->get('installation.points_per_unit'));
    }
}
