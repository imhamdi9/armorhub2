<?php

namespace App\Support;

use App\Models\Department;

/** ArmorHub's own departments. Each one has a head and its own area of permissions that its roles are built from. */
class Departments
{
    /** key => [English, Arabic, permissions the department's roles may use] */
    public const DEFAULT = [
        'customer-service' => ['Customer service', 'خدمة العملاء', ['dashboard.view', 'orders.view', 'orders.update']],
        'procurement' => ['Procurement', 'المشتريات', ['dashboard.view', 'products.view', 'products.create', 'products.update', 'orders.view']],
        'market-managers' => ['Market managers', 'مديرو الأسواق', ['dashboard.view', 'market.settings', 'warehouse.manage', 'partners.view', 'products.view', 'orders.view']],
        'seller-management' => ['Seller management', 'إدارة البائعين', ['dashboard.view', 'companies.view', 'products.view', 'orders.view']],
        'tech' => ['Technology', 'التقنية', ['dashboard.view', 'orders.view']],
        'centre-management' => ['Service centre management', 'إدارة مراكز الخدمة', ['dashboard.view', 'partners.view', 'partners.update', 'orders.view']],
        'warehouse' => ['Warehouse', 'المستودعات', ['dashboard.view', 'warehouse.manage', 'orders.view']],
        'sales' => ['Sales', 'المبيعات', ['dashboard.view', 'orders.view', 'orders.create', 'products.view', 'commissions.view']],
        'disputes' => ['Disputes', 'النزاعات', ['dashboard.view', 'orders.view', 'orders.update']],
        'document-verification' => ['Document verification', 'التحقق من المستندات', ['dashboard.view', 'companies.view', 'partners.view']],
    ];

    public static function ensureDefault(): void
    {
        foreach (self::DEFAULT as $key => [$en, $ar, $scope]) {
            Department::firstOrCreate(['key' => $key], ['name_en' => $en, 'name_ar' => $ar, 'scope' => $scope]);
        }
    }
}
