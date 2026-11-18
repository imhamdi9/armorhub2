<?php

namespace App\Services;

use App\Models\Company;
use App\Models\InstallationInvite;
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
    /** A request goes to this many of the best centres first. */
    public const WAVE_SIZE = 5;
    /** If fewer than this many offers arrive within WIDEN_AFTER_HOURS, the next centres are asked too (up to MAX_WAVES waves). */
    public const MIN_OFFERS = 2;
    public const WIDEN_AFTER_HOURS = 2;
    public const MAX_WAVES = 4;
    public const BANDS_KM = [10, 25, 50, 100];

    /** @return Collection<int, Partner> eligible centres of the request's market, each with `distance_km` and `same_city` set */
    public function eligiblePartners(InstallationRequest $request): Collection
    {
        $tenantId = Company::withoutGlobalScopes()->whereKey($request->company_id)->value('tenant_id');
        $companyIds = Company::withoutGlobalScopes()->where('tenant_id', $tenantId)->pluck('id');

        return Partner::withoutGlobalScopes()
            ->whereIn('company_id', $companyIds)->where('is_active', true)->where('location_enabled', true)
            // A centre of one industry is not asked about another's job (a centre with no industry takes any).
            ->when($request->industry_id, fn ($q, $industry) => $q->where(fn ($w) => $w->whereNull('industry_id')->orWhere('industry_id', $industry)))
            ->whereNotNull('latitude')->whereNotNull('longitude')->get()
            ->each(function (Partner $p) use ($request) {
                $p->distance_km = Geo::km($request->latitude, $request->longitude, (float) $p->latitude, (float) $p->longitude);
                $p->same_city = $request->city && $p->city && mb_strtolower($request->city) === mb_strtolower($p->city);
            })
            ->filter(fn (Partner $p) => $this->willAnswer($p, $request) && $this->inRange($p) && ! $this->tooPoor($p))
            ->values();
    }

    /**
     * Within the centre's reach. A centre that left its radius at the default is asked for its whole city and up to 100 km; one that set a shorter radius
     * is asked only inside it, even in its own city.
     */
    private function inRange(Partner $p): bool
    {
        $radius = (int) ($p->service_radius_km ?: self::RADIUS_KM);

        return $radius < self::RADIUS_KM ? $p->distance_km <= $radius : ($p->same_city || $p->distance_km <= self::RADIUS_KM);
    }

    /** A centre that is full or away, or does not do this kind of work, is not asked. */
    private function willAnswer(Partner $p, InstallationRequest $request): bool
    {
        if (! $p->accepts_requests) {
            return false;
        }

        return empty($p->work_types) || in_array($request->work_type ?: 'install', $p->work_types, true);
    }

    /** A centre may see and answer a request once it was invited (it is among the best centres asked so far) and still qualifies. */
    public function eligible(InstallationRequest $request, Partner $partner): bool
    {
        return InstallationInvite::where('request_id', $request->id)->where('partner_id', $partner->id)->exists()
            && $this->eligiblePartners($request)->contains('id', $partner->id);
    }

    /** Everyone who qualifies, best first: nearest distance band, then the better centre, then the nearer. @return Collection<int, Partner> */
    public function ranked(InstallationRequest $request): Collection
    {
        return $this->eligiblePartners($request)->sort(function (Partner $a, Partner $b) {
            return [$this->band($a->distance_km, (bool) $a->same_city), -$this->quality($a), $a->distance_km] <=> [$this->band($b->distance_km, (bool) $b->same_city), -$this->quality($b), $b->distance_km];
        })->values();
    }

    /** Asks the next best centres that have not been asked yet. @return Collection<int, Partner> those invited */
    public function inviteNext(InstallationRequest $request, int $wave): Collection
    {
        $asked = InstallationInvite::where('request_id', $request->id)->pluck('partner_id');
        $next = $this->ranked($request)->reject(fn (Partner $p) => $asked->contains($p->id))->take(self::WAVE_SIZE)->values();

        foreach ($next as $centre) {
            InstallationInvite::firstOrCreate(['request_id' => $request->id, 'partner_id' => $centre->id], ['wave' => $wave]);
            app(InstallationNotifier::class)->centre($centre->id, 'New installation request near you', ':subject: :parts', '/dashboard/centre-requests', ['subject' => (string) $request->subject, 'parts' => implode(', ', (array) $request->parts)]);
        }
        $request->update(['invite_wave' => $wave]);

        return $next;
    }

    /** Widens every open request that is not getting enough answers. Returns how many were widened. */
    public function widenDue(): int
    {
        $widened = 0;
        InstallationRequest::whereIn('status', ['open', 'offers_received'])->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where('invite_wave', '<', self::MAX_WAVES)->get()->each(function (InstallationRequest $r) use (&$widened) {
                $last = InstallationInvite::where('request_id', $r->id)->max('created_at');
                $offers = InstallationOffer::where('request_id', $r->id)->whereIn('status', ['submitted', 'selected'])->count();
                if ($offers >= self::MIN_OFFERS || ! $last || \Illuminate\Support\Carbon::parse($last)->gt(now()->subHours(self::WIDEN_AFTER_HOURS))) {
                    return;
                }
                if ($this->inviteNext($r, (int) $r->invite_wave + 1)->isNotEmpty()) {
                    $widened++;
                }
            });

        return $widened;
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
