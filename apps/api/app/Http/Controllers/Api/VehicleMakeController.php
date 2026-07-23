<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VehicleMake;
use Illuminate\Http\Request;

class VehicleMakeController extends Controller
{
    public function index()
    {
        return VehicleMake::orderBy('name')->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:vehicle_makes,slug'],
        ]);

        $make = VehicleMake::create($validated);

        return response()->json($make, 201);
    }

    public function show(VehicleMake $vehicle_make)
    {
        return $vehicle_make;
    }

    public function update(Request $request, VehicleMake $vehicle_make)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:vehicle_makes,slug,' . $vehicle_make->id],
        ]);

        $vehicle_make->update($validated);

        return $vehicle_make;
    }

    public function destroy(VehicleMake $vehicle_make)
    {
        $vehicle_make->delete();

        return response()->noContent();
    }
}