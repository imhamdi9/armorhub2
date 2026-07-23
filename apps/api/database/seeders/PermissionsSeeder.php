<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionsSeeder extends Seeder
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

            // Films
            'films.view',
            'films.create',
            'films.update',
            'films.delete',

            // Orders
            'orders.view',
            'orders.create',
            'orders.update',
            'orders.delete',

            // Installation Centers
            'installation-centers.view',
            'installation-centers.create',
            'installation-centers.update',
            'installation-centers.delete',

            // Installation Quotes
            'installation-quotes.view',
            'installation-quotes.update',
            'installation-quotes.accept',

            // Payments
            'payments.create',

            // Commissions
            'commissions.view',
            'commissions.pay',

            // Dashboard
            'dashboard.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }
    }
}