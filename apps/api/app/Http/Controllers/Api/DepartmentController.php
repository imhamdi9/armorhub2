<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/** Platform departments: admins name them and their head; a head runs only their own (members and roles). */
class DepartmentController extends Controller
{
    private function mayManage(Request $request, Department $d): bool
    {
        $u = $request->user();

        return app(\App\Services\Delegation::class)->runs($u, $d) || ($u->is_department_head && $u->department_id === $d->id);
    }

    /** The people who run departments (admin of the platform, admin of the market): they name heads and create departments. */
    private function runsAny(Request $request, ?int $tenantId): bool
    {
        $u = $request->user();

        return $u->hasRole('platform-admin') || ($u->hasRole('company-owner') && $tenantId && $tenantId === \App\Support\MarketScope::tenantId($u));
    }

    public function index(Request $request)
    {
        $u = $request->user();
        $q = Department::query()->orderBy('tenant_id')->orderBy('name_en');
        if ($u->hasRole('platform-admin')) {
            // all
        } elseif ($u->hasRole('company-owner') && ($tenant = \App\Support\MarketScope::tenantId($u))) {
            $q->where('tenant_id', $tenant);
        } else {
            abort_unless($u->is_department_head && $u->department_id, 403);
            $q->whereKey($u->department_id);
        }

        return $q->get()->map(fn (Department $d) => $this->row($d));
    }

    /** A market admin opens the standard departments for their market (the platform admin names the market with ?market=ID). */
    public function open(Request $request)
    {
        $u = $request->user();
        $tenant = $u->hasRole('platform-admin') ? (int) $request->validate(['market' => 'required|integer|exists:tenants,id'])['market'] : \App\Support\MarketScope::tenantId($u);
        abort_unless($this->runsAny($request, $tenant), 403);
        \App\Support\Departments::ensureDefault($tenant);
        \App\Support\Audit::record('department.market_opened', null, ['market' => $tenant], $u);

        return Department::where('tenant_id', $tenant)->orderBy('name_en')->get()->map(fn (Department $d) => $this->row($d));
    }

    public function store(Request $request)
    {
        $tenantId = $request->user()->hasRole('platform-admin') ? ($request->integer('tenant_id') ?: null) : \App\Support\MarketScope::tenantId($request->user());
        abort_unless($request->user()->hasRole('platform-admin') || $this->runsAny($request, $tenantId), 403);
        $request->merge(['tenant_id' => $tenantId]);
        $data = $request->validate([
            'tenant_id' => 'nullable|integer|exists:tenants,id',
            'key' => ['required', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/', 'max:40', Rule::unique('departments', 'key')->where('tenant_id', $tenantId)],
            'name_en' => 'required|string|max:120', 'name_ar' => 'required|string|max:120',
            'scope' => 'required|array|min:1', 'scope.*' => ['string', Rule::in(\App\Http\Controllers\Api\Admin\RoleController::GRANTABLE)],
        ]);
        $d = Department::create($data);
        Audit::record('department.created', $d, ['key' => $d->key], $request->user());

        return response()->json($this->row($d), 201);
    }

    /** Admin: rename and choose the head (a member of the department, or anyone who becomes a member). */
    public function update(Request $request, int $id)
    {
        $d = Department::findOrFail($id);
        abort_unless($this->runsAny($request, $d->tenant_id) || $request->user()->hasRole('platform-admin'), 403);
        $data = $request->validate(['name_en' => 'sometimes|required|string|max:120', 'name_ar' => 'sometimes|required|string|max:120', 'head_user_id' => 'nullable|integer|exists:users,id']);

        if (array_key_exists('head_user_id', $data)) {
            User::where('department_id', $d->id)->update(['is_department_head' => false]);
            if ($data['head_user_id']) {
                $head = User::findOrFail($data['head_user_id']);
                if ($head->hasAnyRole(['customer', 'seller']) || ! $head->company_id
                    || ($d->tenant_id && \App\Support\MarketScope::tenantId($head) !== $d->tenant_id)) {
                    throw ValidationException::withMessages(['head_user_id' => ['A department head must be a member of ArmorHub staff.']]);
                }
                $head->update(['department_id' => $d->id, 'is_department_head' => true]);
            }
            unset($data['head_user_id']);
        }
        $d->update($data);
        Audit::record('department.updated', $d, [], $request->user());

        return $this->row($d->fresh());
    }

    /** What this person may hand to a department's roles: the platform admin anything assignable; a market admin what they hold themselves. */
    private function ceiling(Request $request): array
    {
        $u = $request->user();
        $all = \App\Http\Controllers\Api\Admin\RoleController::assignable();

        return $u->hasRole('platform-admin') ? $all : array_values(array_intersect($all, $u->getAllPermissions()->pluck('name')->all()));
    }

    /** The area of work of a department: which permissions its roles can be built from. Set by the platform admin or the market admin, not in code. */
    public function updateScope(Request $request, int $id)
    {
        $d = Department::findOrFail($id);
        abort_unless(app(\App\Services\Delegation::class)->runs($request->user(), $d), 403);
        $data = $request->validate(['scope' => 'present|array|max:60', 'scope.*' => ['string', Rule::in($this->ceiling($request))]]);
        $d->update(['scope' => array_values(array_unique($data['scope']))]);

        // Roles of the department can never keep what the department no longer covers.
        foreach (Role::where('name', 'like', $d->rolePrefix().'%')->get() as $role) {
            $role->syncPermissions(array_values(array_intersect($role->permissions->pluck('name')->all(), $d->scope)));
        }
        Audit::record('department.scope_updated', $d, ['scope' => $d->scope], $request->user());

        return $this->row($d->fresh());
    }

    public function deleteRole(Request $request, int $id, int $roleId)
    {
        $d = Department::findOrFail($id);
        abort_unless($this->mayManage($request, $d), 403);
        $role = Role::where('name', 'like', $d->rolePrefix().'%')->findOrFail($roleId);
        if (\Illuminate\Support\Facades\DB::table('model_has_roles')->where('role_id', $role->id)->exists()) {
            throw ValidationException::withMessages(['role' => ['Move the people with this role to another role first.']]);
        }
        $role->delete();
        Audit::record('department.role_deleted', $d, ['role' => $role->name], $request->user());

        return response()->noContent();
    }

    public function members(Request $request, int $id)
    {
        $d = Department::findOrFail($id);
        abort_unless($this->mayManage($request, $d), 403);

        return User::where('department_id', $d->id)->with('roles:id,name')->orderBy('name')->get()
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'is_head' => $u->is_department_head, 'is_active' => (bool) $u->is_active, 'roles' => $u->roles->pluck('name')]);
    }

    /** The head changes a member's role (within the department's roles) or switches them off. Never the head themself, never someone else's staff. */
    public function updateMember(Request $request, int $id, int $userId)
    {
        $d = Department::findOrFail($id);
        abort_unless($this->mayManage($request, $d), 403);
        $member = User::where('department_id', $d->id)->findOrFail($userId);
        abort_if($member->is($request->user()), 422, 'You cannot change your own access.');
        $data = $request->validate(['role' => ['sometimes', Rule::in(Role::where('name', 'like', $d->rolePrefix().'%')->pluck('name')->all())], 'is_active' => 'sometimes|boolean']);

        if (isset($data['role'])) {
            $member->syncRoles([$data['role']]);
        }
        if (isset($data['is_active'])) {
            $member->update(['is_active' => $data['is_active']]);
        }
        Audit::record('department.member_updated', $d, ['user' => $member->id] + $data, $request->user());

        return ['ok' => true];
    }

    public function roles(Request $request, int $id)
    {
        $d = Department::findOrFail($id);
        abort_unless($this->mayManage($request, $d), 403);

        return Role::with('permissions:id,name')->where('name', 'like', $d->rolePrefix().'%')->orderBy('name')->get()
            ->map(fn (Role $r) => ['id' => $r->id, 'name' => $r->name, 'label' => substr($r->name, strlen($d->rolePrefix())), 'permissions' => $r->permissions->pluck('name')->sort()->values()]);
    }

    /** A role made of permissions from the department's own area only: the head cannot grant anything beyond it. */
    public function createRole(Request $request, int $id)
    {
        $d = Department::findOrFail($id);
        abort_unless($this->mayManage($request, $d), 403);
        $data = $request->validate([
            'name' => ['required', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/', 'min:2', 'max:30'],
            'permissions' => 'required|array|min:1', 'permissions.*' => ['string', Rule::in($d->scope)],
        ]);
        $name = $d->rolePrefix().$data['name'];
        if (Role::where('name', $name)->exists()) {
            throw ValidationException::withMessages(['name' => ['This role already exists.']]);
        }
        $role = Role::create(['name' => $name, 'guard_name' => 'web']);
        $role->syncPermissions($data['permissions']);
        Audit::record('department.role_created', $d, ['role' => $name], $request->user());

        return response()->json(['id' => $role->id, 'name' => $name], 201);
    }

    public function updateRole(Request $request, int $id, int $roleId)
    {
        $d = Department::findOrFail($id);
        abort_unless($this->mayManage($request, $d), 403);
        $role = Role::where('name', 'like', $d->rolePrefix().'%')->findOrFail($roleId);
        $data = $request->validate(['permissions' => 'required|array|min:1', 'permissions.*' => ['string', Rule::in($d->scope)]]);
        $role->syncPermissions($data['permissions']);
        Audit::record('department.role_updated', $d, ['role' => $role->name], $request->user());

        return ['ok' => true];
    }

    private function row(Department $d): array
    {
        return [
            'can_edit_scope' => (bool) (request()->user() && app(\App\Services\Delegation::class)->runs(request()->user(), $d)),
            'ceiling' => request()->user() && app(\App\Services\Delegation::class)->runs(request()->user(), $d) ? $this->ceiling(request()) : [],'id' => $d->id, 'tenant_id' => $d->tenant_id, 'market' => $d->tenant_id ? \App\Models\Tenant::whereKey($d->tenant_id)->value('name') : null, 'key' => $d->key, 'name_en' => $d->name_en, 'name_ar' => $d->name_ar, 'scope' => $d->scope,
            'head' => User::where('department_id', $d->id)->where('is_department_head', true)->first(['id', 'name', 'email']),
            'members' => User::where('department_id', $d->id)->count()];
    }
}
