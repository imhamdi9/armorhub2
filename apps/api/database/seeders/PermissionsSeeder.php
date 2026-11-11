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

            // Products
            'products.view',
            'products.create',
            'products.update',
            'products.delete',

            // Orders
            'orders.view',
            'orders.create',
            'orders.update',
            'orders.delete',

            // Partners
            'partners.view',
            'partners.create',
            'partners.update',
            'partners.delete',

            // Partner Quotes
            'partner-quotes.view',
            'partner-quotes.update',
            'partner-quotes.accept',

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