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

        return $u->hasRole('platform-admin') || ($u->is_department_head && $u->department_id === $d->id);
    }

    public function index(Request $request)
    {
        $u = $request->user();
        $q = Department::query()->orderBy('name_en');
        if (! $u->hasRole('platform-admin')) {
            abort_unless($u->is_department_head && $u->department_id, 403);
            $q->whereKey($u->department_id);
        }

        return $q->get()->map(fn (Department $d) => $this->row($d));
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->hasRole('platform-admin'), 403);
        $data = $request->validate([
            'key' => ['required', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/', 'max:40', Rule::unique('departments', 'key')],
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
        abort_unless($request->user()->hasRole('platform-admin'), 403);
        $d = Department::findOrFail($id);
        $data = $request->validate(['name_en' => 'sometimes|required|string|max:120', 'name_ar' => 'sometimes|required|string|max:120', 'head_user_id' => 'nullable|integer|exists:users,id']);

        if (array_key_exists('head_user_id', $data)) {
            User::where('department_id', $d->id)->update(['is_department_head' => false]);
            if ($data['head_user_id']) {
                $head = User::findOrFail($data['head_user_id']);
                if ($head->hasAnyRole(['customer', 'seller']) || ! $head->company_id) {
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
        return ['id' => $d->id, 'key' => $d->key, 'name_en' => $d->name_en, 'name_ar' => $d->name_ar, 'scope' => $d->scope,
            'head' => User::where('department_id', $d->id)->where('is_department_head', true)->first(['id', 'name', 'email']),
            'members' => User::where('department_id', $d->id)->count()];
    }
}
