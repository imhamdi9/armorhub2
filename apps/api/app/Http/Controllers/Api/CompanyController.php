<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Models\Company;

class CompanyController extends Controller
{
    use AuthorizesRequests;
    
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
    $this->authorize('viewAny', Company::class);

    return Company::all();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
    $this->authorize('create', Company::class);

    return response()->json([
        'message' => 'Not implemented yet'
    ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Company $company)
   {
    $this->authorize('view', $company);

    return response()->json($company);
   }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Company $company)
    {
    $this->authorize('update', $company);

    return response()->json([
        'message' => 'Not implemented yet'
    ]);
    }
    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Company $company)
    {
    $this->authorize('delete', $company);

    return response()->json([
        'message' => 'Not implemented yet'
    ]);
    }
}
