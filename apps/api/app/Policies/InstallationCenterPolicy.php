<?php

namespace App\Policies;

use App\Models\User;
use App\Models\InstallationCenter;

class InstallationCenterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('installation-centers.view');
    }

    public function view(User $user, InstallationCenter $installationCenter): bool
    {
        return $user->can('installation-centers.view');
    }

    public function create(User $user): bool
    {
        return $user->can('installation-centers.create');
    }

    public function update(User $user, InstallationCenter $installationCenter): bool
    {
        return $user->can('installation-centers.update');
    }

    public function delete(User $user, InstallationCenter $installationCenter): bool
    {
        return $user->can('installation-centers.delete');
    }
}