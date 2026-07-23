<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InstallationCenter;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class InstallationCenterController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorize('viewAny', InstallationCenter::class);

        return InstallationCenter::where(
            'is_active',
            true
        )->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', InstallationCenter::class);

        $center = InstallationCenter::create(
            $request->validate([
                'name' => 'required',
                'city' => 'required',
                'country' => 'required',
                'phone' => 'nullable',
            ])
        );

        return response()->json($center, 201);
    }
}