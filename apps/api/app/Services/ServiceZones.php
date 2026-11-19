<?php

namespace App\Services;

use App\Models\ServiceZone;
use App\Models\User;
use App\Support\Geo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Where a market is open. */
class ServiceZones
{
    /** Whether the market limits itself to zones at all. */
    public function limited(?string $market): bool
    {
        return $market !== null && ServiceZone::where('market', $market)->where('is_active', true)->exists();
    }

    /** The open zone a point falls in (the nearest centre of those that contain it), or null. */
    public function zoneFor(?string $market, float $lat, float $lng): ?ServiceZone
    {
        if ($market === null) {
            return null;
        }

        return ServiceZone::where('market', $market)->where('is_active', true)->get()
            ->map(fn (ServiceZone $z) => [$z, Geo::km($lat, $lng, $z->latitude, $z->longitude)])
            ->filter(fn ($pair) => $pair[1] <= $pair[0]->radius_km)->sortBy(1)->first()[0] ?? null;
    }

    /**
     * A market that is open in zones serves only inside them. A customer outside is told so, and the attempt is kept (rounded, with their id)
     * as demand: it is what says where to open next.
     */
    public function assertServed(?string $market, float $lat, float $lng, User $customer, ?string $city = null): ?ServiceZone
    {
        if (! $this->limited($market)) {
            return null;
        }
        if ($zone = $this->zoneFor($market, $lat, $lng)) {
            return $zone;
        }

        DB::table('zone_waitlist')->insertOrIgnore(['market' => $market, 'customer_id' => $customer->id, 'latitude' => round($lat, 2), 'longitude' => round($lng, 2), 'city' => $city, 'created_at' => now(), 'updated_at' => now()]);

        $open = ServiceZone::where('market', $market)->where('is_active', true)->orderBy('position')->pluck('name_en')->implode(', ');
        throw ValidationException::withMessages(['location' => ["We are not in your area yet. We are open in: {$open}. We noted your interest and will tell you when we open near you."]]);
    }
}
