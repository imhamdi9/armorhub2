<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Platform Admin
        $platform = Role::findByName('platform-admin');
        $platform->syncPermissions(Permission::all());

        // Company Owner
        Role::findByName('company-owner')->syncPermissions([
            'companies.view',
            'companies.update',

            'orders.view',

            'installation-centers.view',
            'installation-centers.create',
            'installation-centers.update',

            'installation-quotes.view',

            'dashboard.view',
        ]);

        // Installer Manager
        Role::findByName('installer-manager')->syncPermissions([
            'installation-centers.view',
            'installation-centers.create',
            'installation-centers.update',

            'installation-quotes.view',
            'installation-quotes.update',
            'installation-quotes.accept',

            'orders.view',

            'dashboard.view',
        ]);

        // Technician
        Role::findByName('technician')->syncPermissions([
            'orders.view',
            'installation-quotes.view',
        ]);

        // Customer
        Role::findByName('customer')->syncPermissions([
            'orders.create',
            'orders.view',
        ]);
    }
}