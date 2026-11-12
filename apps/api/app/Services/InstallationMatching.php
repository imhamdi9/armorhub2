<?php

namespace App\Services;

use App\Models\Company;
use App\Models\InstallationOffer;
use App\Models\InstallationRequest;
use App\Models\Partner;
use App\Support\Geo;
use Illuminate\Support\Collection;

/**
 * Which centres may answer a request, and the order in which the customer sees their offers.
 *
 * Order: distance first, in bands (same city or up to 10 km, then up to 25 km, 50 km, 100 km); inside a band the better centre
 * comes first (rating, speed of answering, how often it answers), then the lower price. Centres with a clearly poor record
 * (5+ ratings averaging under 3) are not shown at all; new centres count as average until they have ratings.
 */
class InstallationMatching
{
    public const RADIUS_KM = 100;
    public const BANDS_KM = [10, 25, 50, 100];

    /** @return Collection<int, Partner> eligible centres of the request's market, each with `distance_km` and `same_city` set */
    public function eligiblePartners(InstallationRequest $request): Collection
    {
        $tenantId = Company::withoutGlobalScopes()->whereKey($request->company_id)->value('tenant_id');
        $companyIds = Company::withoutGlobalScopes()->where('tenant_id', $tenantId)->pluck('id');

        return Partner::withoutGlobalScopes()
            ->whereIn('company_id', $companyIds)->where('is_active', true)->where('location_enabled', true)
            ->whereNotNull('latitude')->whereNotNull('longitude')->get()
            ->each(function (Partner $p) use ($request) {
                $p->distance_km = Geo::km($request->latitude, $request->longitude, (float) $p->latitude, (float) $p->longitude);
                $p->same_city = $request->city && $p->city && mb_strtolower($request->city) === mb_strtolower($p->city);
            })
            ->filter(fn (Partner $p) => ($p->same_city || $p->distance_km <= self::RADIUS_KM) && ! $this->tooPoor($p))
            ->values();
    }

    public function eligible(InstallationRequest $request, Partner $partner): bool
    {
        return $this->eligiblePartners($request)->contains('id', $partner->id);
    }

    public function band(float $km, bool $sameCity): int
    {
        if ($sameCity) {
            return 0;
        }
        foreach (self::BANDS_KM as $i => $max) {
            if ($km <= $max) {
                return $i;
            }
        }

        return count(self::BANDS_KM);
    }

    /** 0..1: rating 50%, how fast the centre answers 25%, how often it answers 25%. Unknown values count as average. */
    public function quality(Partner $p): float
    {
        $rating = $p->rating_count >= 3 && $p->rating_avg !== null ? ((float) $p->rating_avg) / 5 : 0.7;
        $speed = $p->avg_response_minutes === null ? 0.7 : max(0.0, 1 - max(0, $p->avg_response_minutes - 30) / (24 * 60 - 30));
        $accept = $p->acceptance_rate === null ? 0.7 : min(1.0, ((float) $p->acceptance_rate) / 100);

        $base = 0.5 * $rating + 0.25 * $speed + 0.25 * $accept;
        // Earned standing helps a little; lost disputes and strikes cost more than a tier gives.
        $tier = ['gold' => 0.06, 'silver' => 0.03][$p->tier] ?? 0.0;
        $penalty = min(0.25, 0.05 * (int) $p->disputes_lost) + 0.1 * (int) $p->strikes;

        return round(max(0.0, $base + $tier - $penalty), 4);
    }

    private function tooPoor(Partner $p): bool
    {
        return $p->rating_count >= 5 && $p->rating_avg !== null && (float) $p->rating_avg < 3.0;
    }

    /**
     * Offers in the order the customer sees them, with the exact figures the ranking used.
     *
     * @param  Collection<int, InstallationOffer>  $offers  with `partner` loaded
     * @return Collection<int, InstallationOffer>
     */
    public function rank(InstallationRequest $request, Collection $offers): Collection
    {
        return $offers->each(function (InstallationOffer $o) use ($request) {
            $p = $o->partner;
            $km = $o->distance_km ?? Geo::km($request->latitude, $request->longitude, (float) $p->latitude, (float) $p->longitude);
            $sameCity = $request->city && $p->city && mb_strtolower($request->city) === mb_strtolower($p->city);
            $o->setAttribute('band', $this->band($km, (bool) $sameCity));
            $o->setAttribute('quality', $this->quality($p));
            $o->setAttribute('same_city', (bool) $sameCity);
        })->sortBy([['band', 'asc'], ['quality', 'desc'], ['price', 'asc']])->values();
    }
}
