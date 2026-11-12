<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Department;
use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * Who may bring in whom. Nobody can hand out more than their own area:
 *  - platform admin: any role, anywhere (and names department heads)
 *  - market manager: warehouse staff and co-managers of their own market company
 *  - department head: the roles of their own department, inside that department only
 *  - seller: teammates of their own seller company
 *  - centre manager: managers and technicians of their own centre
 *  - customers, technicians and everyone else: nobody
 */
class Delegation
{
    /** @return list<string> role names this person may give */
    public function roles(User $actor, ?Department $dept = null): array
    {
        if ($actor->hasRole('platform-admin')) {
            return Role::pluck('name')->all();
        }
        if ($dept && $actor->is_department_head && $actor->department_id === $dept->id) {
            return Role::where('name', 'like', $dept->rolePrefix().'%')->pluck('name')->all();
        }
        if ($dept) {
            return [];
        }
        if ($actor->hasRole('company-owner') && $actor->company_id) {
            return ['warehouse-staff', 'company-owner'];
        }
        if ($actor->hasRole('seller') && $actor->company_id) {
            return ['seller'];
        }
        if ($actor->hasRole('partner-manager') && $actor->partner_id) {
            return ['technician', 'partner-manager'];
        }

        return [];
    }

    /** The departments this person may invite into (their own, as head; all, as admin). */
    public function departments(User $actor)
    {
        if ($actor->hasRole('platform-admin')) {
            return Department::orderBy('name_en')->get();
        }

        return $actor->is_department_head && $actor->department_id ? Department::whereKey($actor->department_id)->get() : collect();
    }

    /** @return array{0: ?int, 1: ?int} company and partner a new person is placed in: always the inviter's own, except for admins. */
    public function placement(User $actor, ?int $company, ?int $partner, ?Department $dept): array
    {
        if ($actor->hasRole('platform-admin')) {
            $company ??= $dept ? $this->platformCompanyId() : null;

            return [$company, $partner];
        }
        if ($dept) {
            return [$this->platformCompanyId() ?? $actor->company_id, null];
        }

        return [$actor->company_id, $actor->hasRole('partner-manager') ? $actor->partner_id : null];
    }

    private function platformCompanyId(): ?int
    {
        return Company::withoutGlobalScopes()->where('type', 'platform')->orderBy('id')->value('id');
    }
}
