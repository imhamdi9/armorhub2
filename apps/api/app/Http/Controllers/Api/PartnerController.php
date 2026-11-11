<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class PartnerController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $this->authorize('viewAny', Partner::class);

        return Partner::where('is_active', true)->orderBy('name')->get();
    }

    public function store(Request $request)
    {
        $this->authorize('create', Partner::class);

        $partner = Partner::create(
            $this->validated($request) + ['company_id' => $request->user()->company_id]
        );

        return response()->json($partner, 201);
    }

    public function show(Partner $partner)
    {
        $this->authorize('view', $partner);

        return $partner;
    }

    public function update(Request $request, Partner $partner)
    {
        $this->authorize('update', $partner);

        $partner->update($this->validated($request, true));

        return $partner->fresh();
    }

    public function destroy(Partner $partner)
    {
        $this->authorize('delete', $partner);

        $partner->delete();

        return response()->json(['message' => 'Deleted']);
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes|required' : 'required';

        return $request->validate([
            'name' => "$required|string|max:255",
            'city' => "$required|string|max:255",
            'country' => "$required|string|max:255",
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'location_enabled' => 'boolean',
            'is_active' => 'boolean',
        ]);
    }
}
