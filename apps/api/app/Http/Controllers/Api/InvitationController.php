<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\InvitationSent;
use App\Services\Delegation;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InvitationController extends Controller
{
    public function __construct(private Delegation $delegation)
    {
    }

    /** What the signed-in person may invite: roles (per department too) and the departments. */
    public function options(Request $request)
    {
        $u = $request->user();

        return [
            'roles' => $this->delegation->roles($u),
            'departments' => $this->delegation->departments($u)->map(fn (Department $d) => ['id' => $d->id, 'key' => $d->key, 'name' => $d->name_en, 'name_ar' => $d->name_ar, 'roles' => $this->delegation->roles($u, $d)])->values(),
        ];
    }

    public function index(Request $request)
    {
        $u = $request->user();

        return Invitation::query()->when(! $u->hasRole('platform-admin'), fn ($q) => $q->where('invited_by', $u->id))
            ->latest('id')->limit(100)->get()
            ->map(fn (Invitation $i) => ['id' => $i->id, 'email' => $i->email, 'role' => $i->role, 'department_id' => $i->department_id,
                'status' => $i->accepted_at ? 'accepted' : ($i->expires_at->isPast() ? 'expired' : 'pending'), 'expires_at' => $i->expires_at]);
    }

    public function store(Request $request)
    {
        $actor = $request->user();
        $data = $request->validate([
            'email' => 'required|email|max:255',
            'role' => 'required|string',
            'department_id' => 'nullable|integer|exists:departments,id',
            'as_head' => 'sometimes|boolean',
            'company_id' => 'nullable|integer|exists:companies,id',
            'partner_id' => 'nullable|integer|exists:partners,id',
        ]);
        $dept = isset($data['department_id']) ? Department::find($data['department_id']) : null;

        if (! in_array($data['role'], $this->delegation->roles($actor, $dept), true)) {
            throw ValidationException::withMessages(['role' => ['You cannot invite people into this role.']]);
        }
        if (! empty($data['as_head']) && (! $dept || ! $this->delegation->runs($actor, $dept))) {
            throw ValidationException::withMessages(['as_head' => ['Only a platform admin or the market admin names a department head.']]);
        }
        if (! empty($data['as_head']) && ! str_starts_with($data['role'], $dept->rolePrefix())) {
            throw ValidationException::withMessages(['role' => ['A department head holds one of the department\'s own roles. Create it first.']]);
        }
        $email = mb_strtolower(trim($data['email']));
        if (User::where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => ['This person already has an account.']]);
        }
        [$company, $partner] = $this->delegation->placement($actor, $data['company_id'] ?? null, $data['partner_id'] ?? null, $dept);
        // Anyone outside the platform is tied to one company, so every screen is scoped to it.
        if (! $company && ! $actor->hasRole('platform-admin')) {
            throw ValidationException::withMessages(['role' => ['You cannot invite people.']]);
        }

        // A new invitation replaces an older open one for the same person.
        Invitation::where('email', $email)->where('invited_by', $actor->id)->whereNull('accepted_at')->delete();
        $token = Str::random(48);
        $inv = Invitation::create([
            'token_hash' => hash('sha256', $token), 'email' => $email, 'role' => $data['role'], 'invited_by' => $actor->id, 'company_id' => $company,
            'partner_id' => $partner, 'department_id' => $dept?->id, 'as_head' => (bool) ($data['as_head'] ?? false), 'expires_at' => now()->addDays(7),
        ]);
        Notification::route('mail', $email)->notify(new InvitationSent($actor->name, $token));
        Audit::record('invitation.sent', $inv, ['email' => $email, 'role' => $data['role']], $actor);

        return response()->json(['id' => $inv->id, 'email' => $email, 'role' => $inv->role, 'status' => 'pending'], 201);
    }

    public function destroy(Request $request, int $id)
    {
        $u = $request->user();
        $inv = Invitation::whereNull('accepted_at')->when(! $u->hasRole('platform-admin'), fn ($q) => $q->where('invited_by', $u->id))->findOrFail($id);
        $inv->delete();

        return response()->noContent();
    }

    /** Public: who invited you, and to what (so the page can greet the person). */
    public function show(string $token)
    {
        $inv = $this->open($token);

        return ['email' => $inv->email, 'role' => $inv->role, 'inviter' => $inv->inviter?->name];
    }

    public function accept(Request $request, string $token)
    {
        $inv = $this->open($token);
        $data = $request->validate(['name' => 'required|string|max:255', 'password' => \App\Rules\StrongPassword::rules()]);

        $user = DB::transaction(function () use ($inv, $data) {
            if (User::where('email', $inv->email)->exists()) {
                throw ValidationException::withMessages(['email' => ['This person already has an account.']]);
            }
            $user = User::create([
                'name' => $data['name'], 'email' => $inv->email, 'password' => $data['password'], 'email_verified_at' => now(),
                'company_id' => $inv->company_id, 'partner_id' => $inv->partner_id, 'department_id' => $inv->department_id, 'is_department_head' => $inv->as_head,
            ]);
            $user->assignRole($inv->role);
            $inv->update(['accepted_at' => now()]);

            return $user;
        });
        Audit::record('invitation.accepted', $inv, ['user' => $user->id], $user);

        return response()->json(['message' => 'Account created. You can sign in now.'], 201);
    }

    private function open(string $token): Invitation
    {
        $inv = Invitation::with('inviter:id,name')->where('token_hash', hash('sha256', $token))->first();
        abort_unless($inv && $inv->isOpen(), 404);

        return $inv;
    }
}
