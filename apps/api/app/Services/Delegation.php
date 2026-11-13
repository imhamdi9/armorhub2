<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Department;
use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * Who may bring in whom. Nobody can hand out more than their own area:
 *  - platform admin: any role, anywhere (and names department heads)
 *  - market admin: warehouse staff of their own market (only the platform owner makes other market admins)
 *  - department head: the roles of their own department, inside that department only
 *  - seller: teammates of their own seller company
 *  - centre manager: managers and technicians of their own centre
 *  - customers, technicians and everyone else: nobody
 */
class Delegation
{
    /** The person runs this department's market (a market admin of that very market), or is the platform admin. */
    public function runs(User $actor, Department $dept): bool
    {
        if ($actor->hasRole('platform-admin')) {
            return true;
        }

        return $actor->hasRole('company-owner') && $dept->tenant_id && $dept->tenant_id === \App\Support\MarketScope::tenantId($actor);
    }

    /** @return list<string> role names this person may give */
    public function roles(User $actor, ?Department $dept = null): array
    {
        if ($actor->hasRole('platform-admin')) {
            return Role::pluck('name')->all();
        }
        if ($dept && $this->runs($actor, $dept)) {
            return Role::where('name', 'like', $dept->rolePrefix().'%')->pluck('name')->all();
        }
        if ($dept && $actor->is_department_head && $actor->department_id === $dept->id) {
            return Role::where('name', 'like', $dept->rolePrefix().'%')->pluck('name')->all();
        }
        if ($dept) {
            return [];
        }
        if ($actor->hasRole('company-owner') && $actor->company_id) {
            return ['warehouse-staff'];
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
        if ($actor->hasRole('company-owner') && ($tenant = \App\Support\MarketScope::tenantId($actor))) {
            return Department::where('tenant_id', $tenant)->orderBy('name_en')->get();
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
            // A market's department places people in the market's own company; a platform department in the platform company.
            return [$dept->tenant_id ? $actor->company_id : ($this->platformCompanyId() ?? $actor->company_id), null];
        }

        return [$actor->company_id, $actor->hasRole('partner-manager') ? $actor->partner_id : null];
    }

    private function platformCompanyId(): ?int
    {
        return Company::withoutGlobalScopes()->where('type', 'platform')->orderBy('id')->value('id');
    }
}
