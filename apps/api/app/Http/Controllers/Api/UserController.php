<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Staff management. Who may create whom:
 *  - platform-admin: anyone, anywhere
 *  - company-owner:  staff inside their own company
 *  - partner-manager: technicians of their own partner
 */
class UserController extends Controller
{
    private const ROLES_BY_MANAGER = [
        'platform-admin' => ['platform-admin', 'company-owner', 'partner-manager', 'technician', 'customer'],
        'company-owner' => ['company-owner', 'partner-manager', 'technician'],
        'partner-manager' => ['technician'],
    ];

    public function index(Request $request)
    {
        return $this->visible($request->user())
            ->with(['roles:id,name', 'company:id,name', 'partner:id,name'])
            ->when($request->filled('search'), fn ($q) => $q->where(
                fn ($w) => $w->where('name', 'like', "%{$request->search}%")->orWhere('email', 'like', "%{$request->search}%")
            ))
            ->orderBy('name')
            ->paginate(25)
            ->through(fn (User $u) => $this->present($u));
    }

    public function store(Request $request)
    {
        $actor = $request->user();
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => \App\Rules\StrongPassword::rules(),
            'role' => ['required', Rule::in($this->allowedRoles($actor))],
            'company_id' => 'nullable|integer|exists:companies,id',
            'partner_id' => 'nullable|integer|exists:partners,id',
        ]);

        [$companyId, $partnerId] = $this->placement($actor, $data['company_id'] ?? null, $data['partner_id'] ?? null);
        $this->requireCompanyForCustomRole($data['role'], $companyId);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            // An administrator vouches for the people they add, so no verification email is needed.
            'email_verified_at' => now(),
            'company_id' => $companyId,
            'partner_id' => $partnerId,
        ]);
        $user->assignRole($data['role']);

        return response()->json($this->present($user->load(['roles:id,name', 'company:id,name', 'partner:id,name'])), 201);
    }

    public function update(Request $request, int $id)
    {
        $actor = $request->user();
        $user = $this->visible($actor)->findOrFail($id);

        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'password' => ['sometimes', ...\App\Rules\StrongPassword::rules()],
            'role' => ['sometimes', Rule::in($this->allowedRoles($actor))],
            'is_active' => 'sometimes|boolean',
        ]);

        // Nobody may change their own role or lock themselves out.
        if ($user->is($actor) && (isset($data['role']) || (isset($data['is_active']) && ! $data['is_active']))) {
            throw ValidationException::withMessages(['user' => ['You cannot change your own role or deactivate yourself.']]);
        }

        // Managers may not edit people more powerful than what they can create.
        $this->assertManageable($actor, $user);

        if (isset($data['role'])) {
            $this->requireCompanyForCustomRole($data['role'], $user->company_id);
            $user->syncRoles([$data['role']]);
        }

        $user->update(collect($data)->only(['name', 'password', 'is_active'])->all());

        if (isset($data['is_active']) && ! $data['is_active']) {
            $user->tokens()->delete(); // sign them out everywhere
        }

        return $this->present($user->fresh(['roles:id,name', 'company:id,name', 'partner:id,name']));
    }

    /** Sign a person out everywhere, e.g. after a lost phone or a suspected account takeover. */
    public function revokeSessions(Request $request, int $id)
    {
        $user = $this->visible($request->user())->findOrFail($id);
        $this->assertManageable($request->user(), $user);

        $count = $user->tokens()->count();
        $user->tokens()->delete();

        \App\Support\Audit::record('auth.sessions_revoked', $user, ['sessions' => $count]);

        return response()->json(['message' => "Signed out of {$count} session(s)."]);
    }

    public function destroy(Request $request, int $id)
    {
        $actor = $request->user();
        $user = $this->visible($actor)->findOrFail($id);

        abort_if($user->is($actor), 422, 'You cannot delete yourself.');
        $this->assertManageable($actor, $user);

        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'Deleted']);
    }

    /** Users the actor is allowed to see/manage. */
    private function visible(User $actor)
    {
        $query = User::query();

        if ($actor->hasRole('platform-admin')) {
            return $query;
        }

        if ($actor->hasRole('company-owner') && $actor->company_id) {
            return $query->where('company_id', $actor->company_id);
        }

        if ($actor->hasRole('partner-manager') && $actor->partner_id) {
            return $query->where('partner_id', $actor->partner_id);
        }

        return $query->whereRaw('1 = 0');
    }

    /** @return list<string> */
    private function allowedRoles(User $actor): array
    {
        foreach (self::ROLES_BY_MANAGER as $role => $allowed) {
            if ($actor->hasRole($role)) {
                // Only platform admins hand out custom roles.
                return $role === 'platform-admin' ? [...$allowed, ...\App\Http\Controllers\Api\Admin\RoleController::customNames()] : $allowed;
            }
        }

        return [];
    }

    /** Force company/partner to the actor's own, unless they are a platform admin. */
    private function placement(User $actor, ?int $companyId, ?int $partnerId): array
    {
        if ($actor->hasRole('platform-admin')) {
            return [$companyId, $partnerId];
        }

        if ($actor->hasRole('partner-manager')) {
            return [$actor->company_id, $actor->partner_id];
        }

        // company-owner: own company; a partner must belong to it (Partner is company-scoped).
        if ($partnerId !== null && ! Partner::whereKey($partnerId)->exists()) {
            throw ValidationException::withMessages(['partner_id' => ['That partner is not in your company.']]);
        }

        return [$actor->company_id, $partnerId];
    }

    /** Custom roles are company staff: without a company they would have no data to work with, and nothing to be scoped by. */
    private function requireCompanyForCustomRole(string $role, ?int $companyId): void
    {
        if (! in_array($role, \App\Http\Controllers\Api\Admin\RoleController::BUILT_IN, true) && ! $companyId) {
            throw ValidationException::withMessages(['company_id' => ['Choose a company for this role.']]);
        }
    }

    private function assertManageable(User $actor, User $target): void
    {
        $allowed = $this->allowedRoles($actor);

        abort_unless(
            $target->roles->isEmpty() || $target->roles->pluck('name')->every(fn ($r) => in_array($r, $allowed, true)),
            403,
            'You cannot manage this user.'
        );
    }

    private function present(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => $user->is_active,
            'role' => $user->roles->first()?->name,
            'company' => $user->company?->only(['id', 'name']),
            'partner' => $user->partner?->only(['id', 'name']),
        ];
    }
}
