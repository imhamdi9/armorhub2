<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceZone;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** The areas a market is open in, and the demand outside them. Platform administrators only. */
class ZoneController extends Controller
{
    public function index(Request $request)
    {
        $market = $request->query('market');

        return ServiceZone::when($market, fn ($q) => $q->where('market', strtoupper((string) $market)))->orderBy('market')->orderBy('position')->get()->map(fn ($z) => $this->row($z))->values();
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $zone = ServiceZone::create($data + ['position' => (int) ServiceZone::where('market', $data['market'])->max('position') + 1]);
        Audit::record('zones.created', $zone, ['key' => $zone->key], $request->user());

        return response()->json($this->row($zone), 201);
    }

    public function update(Request $request, int $id)
    {
        $zone = ServiceZone::findOrFail($id);
        $zone->update($this->validated($request, $zone));
        Audit::record('zones.updated', $zone, ['active' => $zone->is_active], $request->user());

        return $this->row($zone->fresh());
    }

    public function destroy(Request $request, int $id)
    {
        $zone = ServiceZone::findOrFail($id);
        $zone->delete();
        Audit::record('zones.deleted', null, ['key' => $zone->key], $request->user());

        return response()->json(['message' => 'Deleted']);
    }

    /** Where customers asked outside the open zones, grouped by city: the places to open next. */
    public function demand(Request $request)
    {
        return DB::table('zone_waitlist')->when($request->query('market'), fn ($q, $m) => $q->where('market', strtoupper((string) $m)))
            ->selectRaw('market, coalesce(city, \'(unknown)\') as city, round(avg(latitude), 2) as latitude, round(avg(longitude), 2) as longitude, count(*) as customers')
            ->groupBy('market', 'city')->orderByDesc('customers')->limit(50)->get();
    }

    private function validated(Request $request, ?ServiceZone $zone = null): array
    {
        $sometimes = $zone ? 'sometimes|' : '';

        $data = $request->validate([
            'market' => [...($zone ? ['sometimes'] : []), 'required', 'string', 'size:2', 'alpha'],
            'key' => [...($zone ? ['sometimes'] : []), 'required', 'string', 'max:60', 'regex:/^[a-z0-9][a-z0-9-]*$/', Rule::unique('service_zones', 'key')->where(fn ($q) => $q->where('market', strtoupper((string) $request->input('market', $zone?->market))))->ignore($zone?->id)],
            'name_en' => $sometimes.'required|string|max:120',
            'name_ar' => 'nullable|string|max:120',
            'city' => 'nullable|string|max:80',
            'latitude' => $sometimes.'required|numeric|between:-90,90',
            'longitude' => $sometimes.'required|numeric|between:-180,180',
            'radius_km' => 'sometimes|integer|min:1|max:200',
            'is_active' => 'sometimes|boolean',
        ]);
        if (isset($data['market'])) {
            $data['market'] = strtoupper($data['market']);
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function row(ServiceZone $z): array
    {
        return ['id' => $z->id, 'market' => $z->market, 'key' => $z->key, 'name_en' => $z->name_en, 'name_ar' => $z->name_ar, 'city' => $z->city, 'latitude' => $z->latitude, 'longitude' => $z->longitude,
            'radius_km' => $z->radius_km, 'is_active' => $z->is_active, 'position' => $z->position,
            'centres' => \App\Models\Partner::withoutGlobalScopes()->where('is_active', true)->where('location_enabled', true)->whereNotNull('latitude')->get()
                ->filter(fn ($p) => \App\Support\Geo::km((float) $p->latitude, (float) $p->longitude, $z->latitude, $z->longitude) <= $z->radius_km)->count()];
    }
}
