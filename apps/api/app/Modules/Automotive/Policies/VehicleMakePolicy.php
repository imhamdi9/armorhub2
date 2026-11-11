<?php

namespace App\Modules\Automotive\Policies;

use App\Models\User;
use App\Modules\Automotive\Models\VehicleMake;

class VehicleMakePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('vehicle-makes.view');
    }

    public function view(User $user, VehicleMake $vehicleMake): bool
    {
        return $user->can('vehicle-makes.view');
    }

    public function create(User $user): bool
    {
        return $user->can('vehicle-makes.create');
    }

    public function update(User $user, VehicleMake $vehicleMake): bool
    {
        return $user->can('vehicle-makes.update');
    }

    public function delete(User $user, VehicleMake $vehicleMake): bool
    {
        return $user->can('vehicle-makes.delete');
    }
}