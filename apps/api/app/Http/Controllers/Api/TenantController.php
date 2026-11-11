<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function index()
    {
        return Tenant::with('industry')->orderBy('name')->get();
    }

    public function store(Request $request)
    {
        $tenant = Tenant::create($request->validate([
            'name' => 'required|string|max:255',
            'industry_id' => 'required|exists:industries,id',
            'market' => ['nullable', 'string', \Illuminate\Validation\Rule::in(app(\App\Support\Markets\Markets::class)->codes())],
        ]));

        return response()->json($tenant->load('industry'), 201);
    }

    public function update(Request $request, Tenant $tenant)
    {
        $tenant->update($request->validate([
            'name' => 'sometimes|required|string|max:255',
            'industry_id' => 'sometimes|required|exists:industries,id',
            'market' => ['sometimes', 'nullable', 'string', \Illuminate\Validation\Rule::in(app(\App\Support\Markets\Markets::class)->codes())],
        ]));

        return $tenant->load('industry');
    }
}
