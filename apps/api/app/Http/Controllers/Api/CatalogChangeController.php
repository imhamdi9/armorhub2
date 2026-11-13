<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CatalogChange;
use App\Models\User;
use App\Services\CatalogChanges;
use App\Support\MarketScope;
use Illuminate\Http\Request;

/** Proposed catalog and category changes: market admins see their own market's, the platform admin sees all and decides. */
class CatalogChangeController extends Controller
{
    public function __construct(private CatalogChanges $changes)
    {
    }

    public function index(Request $request)
    {
        $u = $request->user();
        $status = $request->query('status');

        return CatalogChange::query()
            ->when(! $u->hasRole('platform-admin'), fn ($q) => $q->where('tenant_id', MarketScope::tenantId($u)))
            ->when(in_array($status, ['pending', 'approved', 'rejected'], true), fn ($q) => $q->where('status', $status))
            ->latest('id')->limit(100)->get()
            ->map(fn (CatalogChange $c) => $c->only(['id', 'target', 'action', 'record_id', 'payload', 'summary', 'status', 'note', 'created_at', 'decided_at'])
                + ['market' => $c->tenant_id ? \App\Models\Tenant::whereKey($c->tenant_id)->value('name') : null, 'requested_by' => User::whereKey($c->requested_by)->value('name')]);
    }

    public function approve(Request $request, int $id)
    {
        return $this->changes->approve(CatalogChange::findOrFail($id), $request->user())->only(['id', 'status']);
    }

    public function reject(Request $request, int $id)
    {
        $note = $request->validate(['note' => 'required|string|min:3|max:300'])['note'];

        return $this->changes->reject(CatalogChange::findOrFail($id), $request->user(), $note)->only(['id', 'status']);
    }
}
