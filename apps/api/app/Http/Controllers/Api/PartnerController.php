<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class PartnerController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorize('viewAny', Partner::class);

        return Partner::where(
            'is_active',
            true
        )->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Partner::class);

        $center = Partner::create(
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