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

    // Products
    'products.view',
    'products.create',
    'products.update',
    'products.delete',

    // Tenants / industries
    'tenants.manage',

    // Staff
    'users.manage',

    // The payment, shipping and fee settings of one's own market (market managers); the platform-wide ones stay with admins
    'market.settings',
    // Receiving, counting and shipping the goods ArmorHub stores for sellers in a market
    'warehouse.manage',
    // Platform administration (admins only: they get every permission)
    'admin.panel',
    'catalog.manage',

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
    'commissions.pay',

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
    'market.settings',
    'warehouse.manage',
    'companies.view',
    'companies.update',

    'vehicle-makes.view',

    'products.view',
    'products.create',
    'products.update',
    'products.delete',

    'catalog.manage',

    'partners.view',
    'partners.create',
    'partners.update',

    'users.manage',

    'orders.view',
    'orders.update',

    'pricing.calculate',

    'commissions.view',

    'dashboard.view',
],

'partner-manager' => [
    'partners.view',
    'partners.create',
    'partners.update',

    'products.view',

    'users.manage',

    'orders.view',

    'pricing.calculate',

    'commissions.view',

    'dashboard.view',
],

// The team of a market's warehouse: receives sellers' cartons, counts stock, packs and ships orders.
'warehouse-staff' => [
    'warehouse.manage',
    'orders.view',
    'dashboard.view',
],

'technician' => [
    'partners.view',

    'orders.view',
],

// A verified business selling its own products inside a marketplace.
'seller' => [
    'products.view',
    'products.create',
    'products.update',
    'products.delete',

    'vehicle-makes.view',

    'dashboard.view',
],

'customer' => [
    'orders.create',

    'payments.create',

    'products.view',

    'vehicle-makes.view',

    'pricing.calculate',
],
]; // 

        // The market admin holds every permission except the platform-level ones (markets, platform settings); the data is limited to their market.
        $roles['company-owner'] = array_values(array_unique(array_merge($roles['company-owner'], array_diff($permissions, ['tenants.manage', 'admin.panel', 'companies.create', 'companies.delete']))));

        foreach ($roles as $role => $abilities) {

            $newRole = Role::firstOrCreate([
                'name' => $role,
            ]);


            $newRole->syncPermissions($abilities);

        }
    }
}