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

            'partners.view',
            'partners.create',
            'partners.update',

            'partner-quotes.view',

            'dashboard.view',
        ]);

        // Partner Manager
        Role::findByName('partner-manager')->syncPermissions([
            'partners.view',
            'partners.create',
            'partners.update',

            'partner-quotes.view',
            'partner-quotes.update',
            'partner-quotes.accept',

            'orders.view',

            'dashboard.view',
        ]);

        // Technician
        Role::findByName('technician')->syncPermissions([
            'orders.view',
            'partner-quotes.view',
        ]);

        // Customer
        Role::findByName('customer')->syncPermissions([
            'orders.create',
            'orders.view',
        ]);
    }
}