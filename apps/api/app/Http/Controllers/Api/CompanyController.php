<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $this->authorize('viewAny', Company::class);

        return Company::orderBy('name')->get();
    }

    public function store(Request $request)
    {
        $this->authorize('create', Company::class);

        $company = Company::create($this->validated($request, true));

        return response()->json($company, 201);
    }

    public function show(Company $company)
    {
        $this->authorize('view', $company);

        return response()->json($company);
    }

    public function update(Request $request, Company $company)
    {
        $this->authorize('update', $company);

        $company->update($this->validated($request));

        return response()->json($company->fresh());
    }

    public function destroy(Company $company)
    {
        $this->authorize('delete', $company);

        $company->delete();

        return response()->json(['message' => 'Deleted']);
    }

    private function validated(Request $request, bool $creating = false): array
    {
        $required = $creating ? 'required' : 'sometimes|required';

        // Only platform admins may change what kind of company this is, or its standing.
        $adminOnly = $request->user()->hasRole('platform-admin')
            ? [
                'type' => 'sometimes|in:platform,partner,supplier',
                'status' => 'sometimes|in:pending,active,inactive,suspended',
            ]
            : [];

        return $request->validate($adminOnly + [
            'tenant_id' => [$creating ? 'required' : 'prohibited', 'integer', 'exists:tenants,id'],
            'name' => "$required|string|max:255",
            'legal_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
        ]);
    }
}
