<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;


class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {

        $permissions = [

            'manage platform',
            'manage companies',
            'manage branches',
            'manage vehicles',
            'manage products',
            'manage orders',
            'manage bookings',
            'view reports',

        ];


        foreach ($permissions as $permission) {

            Permission::firstOrCreate([
                'name' => $permission,
            ]);

        }


        $roles = [

            'platform-admin' => $permissions,

            'company-owner' => [
                'manage branches',
                'manage vehicles',
                'manage orders',
                'manage bookings',
                'view reports',
            ],

            'installer-manager' => [
                'manage bookings',
                'manage orders',
            ],

            'technician' => [
                'manage bookings',
            ],

            'customer' => [],

        ];


        foreach ($roles as $role => $abilities) {

            $newRole = Role::firstOrCreate([
                'name' => $role,
            ]);


            $newRole->syncPermissions($abilities);

        }
    }
}