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

    // Companies
    'companies.view',
    'companies.create',
    'companies.update',
    'companies.delete',

    // Vehicle Makes
    'vehicle-makes.view',
    'vehicle-makes.create',
    'vehicle-makes.update',
    'vehicle-makes.delete',

    // Partners
    'partners.view',
    'partners.create',
    'partners.update',
    'partners.delete',

    // Orders
    'orders.view',
    'orders.create',
    'orders.update',
    'orders.delete',

    // Pricing
    'pricing.calculate',

    // Payments
    'payments.create',

    // Commissions
    'commissions.view',

    // Dashboard
    'dashboard.view',
];


        foreach ($permissions as $permission) {

            Permission::firstOrCreate([
                'name' => $permission,
            ]);

        }


        $roles = [

            'platform-admin' => $permissions,

            'company-owner' => [
    'companies.view',
    'companies.create',
    'companies.update',

    'vehicle-makes.view',

    'orders.view',

    'dashboard.view',
],

'partner-manager' => [
    'partners.view',
    'partners.create',
    'partners.update',

    'orders.view',

    'pricing.calculate',

    'dashboard.view',
],

'technician' => [
    'partners.view',

    'orders.view',
],

'customer' => [
    'orders.create',

    'vehicle-makes.view',

    'pricing.calculate',
],
]; // 

        foreach ($roles as $role => $abilities) {

            $newRole = Role::firstOrCreate([
                'name' => $role,
            ]);


            $newRole->syncPermissions($abilities);

        }
    }
}