<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Custom roles: a named bundle of everyday permissions for company staff. Built-in roles cannot be touched, and the grantable
 * permissions are a short allow-list that leaves out anything administrative, destructive or money-moving, so a custom role
 * can never be used to escalate privileges. People holding a custom role are always tied to one company (data scoping).
 */
class RoleController extends Controller
{
    public const BUILT_IN = ['platform-admin', 'company-owner', 'partner-manager', 'technician', 'customer', 'seller', 'warehouse-staff'];

    public const GRANTABLE = [
        'dashboard.view', 'orders.view', 'products.view', 'products.create', 'products.update', 'partners.view', 'partners.update',
        'companies.view', 'commissions.view', 'pricing.calculate', 'vehicle-makes.view',
    ];

    /** Everything a platform admin may hand to a role, except the two platform-level powers (running markets and the platform panel). */
    public static function assignable(): array
    {
        return \Spatie\Permission\Models\Permission::whereNotIn('name', ['admin.panel', 'tenants.manage'])->orderBy('name')->pluck('name')->all();
    }

    public function index()
    {
        $counts = DB::table('model_has_roles')->selectRaw('role_id, count(*) as n')->groupBy('role_id')->pluck('n', 'role_id');

        return [
            'grantable' => self::GRANTABLE,
            'assignable' => self::assignable(),
            'roles' => Role::with('permissions:id,name')->orderBy('name')->get()->map(fn (Role $r) => [
                'id' => $r->id, 'name' => $r->name, 'built_in' => in_array($r->name, self::BUILT_IN, true), 'editable' => $r->name !== 'platform-admin',
                'permissions' => $r->permissions->pluck('name')->sort()->values(), 'users' => (int) ($counts[$r->id] ?? 0),
            ])->values(),
        ];
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/', Rule::notIn(self::BUILT_IN), Rule::unique('roles', 'name')],
            'permissions' => 'present|array|max:20',
            'permissions.*' => ['string', Rule::in(self::GRANTABLE)],
        ]);

        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        $role->syncPermissions($data['permissions']);
        Audit::record('role.created', null, ['role' => $role->name, 'permissions' => $data['permissions']]);

        return response()->json(['id' => $role->id], 201);
    }

    public function update(Request $request, int $id)
    {
        $role = Role::findOrFail($id);
        abort_if($role->name === 'platform-admin', 403, 'The platform admin always holds every permission.');
        // Built-in roles can be tuned too (everything except the platform-level powers); custom roles stay within the everyday allow-list.
        $allowed = in_array($role->name, self::BUILT_IN, true) ? self::assignable() : self::GRANTABLE;
        $data = $request->validate(['permissions' => 'present|array|max:60', 'permissions.*' => ['string', Rule::in($allowed)]]);

        $role->syncPermissions($data['permissions']);
        Audit::record('role.updated', null, ['role' => $role->name, 'permissions' => $data['permissions']]);

        return ['ok' => true];
    }

    public function destroy(int $id)
    {
        $role = $this->custom($id);

        if (DB::table('model_has_roles')->where('role_id', $role->id)->exists()) {
            throw ValidationException::withMessages(['role' => ['Move the people with this role to another role first.']]);
        }

        $role->delete();
        Audit::record('role.deleted', null, ['role' => $role->name]);

        return response()->noContent();
    }

    private function custom(int $id): Role
    {
        $role = Role::findOrFail($id);
        abort_if(in_array($role->name, self::BUILT_IN, true), 403, 'Built-in roles cannot be changed.');

        return $role;
    }

    /** @return list<string> */
    public static function customNames(): array
    {
        return Role::whereNotIn('name', self::BUILT_IN)->pluck('name')->all();
    }
}
