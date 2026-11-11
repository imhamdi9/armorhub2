<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Industry;
use App\Models\Partner;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Command;

class SeedDemo extends Command
{
    protected $signature = 'platform:demo {--password=password123 : Password for all demo users}';

    protected $description = 'Create a demo automotive marketplace (admin, storefront, partner) for local use and E2E tests';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Refusing to create demo data in production.');

            return self::FAILURE;
        }

        $this->callSilent('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);

        $password = $this->option('password');

        $admin = User::create(['name' => 'Admin', 'email' => 'admin@test.com', 'password' => $password]);
        $admin->assignRole('platform-admin');

        $tenant = Tenant::create([
            'name' => 'Armor',
            'industry_id' => Industry::where('key', 'automotive')->value('id'),
        ]);
        $store = Company::create(['tenant_id' => $tenant->id, 'name' => 'Armor Store']);
        $shopCompany = Company::create(['tenant_id' => $tenant->id, 'name' => 'Shop Co', 'type' => 'partner']);

        $partner = Partner::create([
            'company_id' => $shopCompany->id,
            'name' => 'Shop One', 'city' => 'Riyadh', 'country' => 'SA',
            'latitude' => 24.7, 'longitude' => 46.7, 'location_enabled' => true,
        ]);

        $manager = User::create([
            'name' => 'Shop Manager', 'email' => 'shop@test.com', 'password' => $password,
            'company_id' => $shopCompany->id, 'partner_id' => $partner->id,
        ]);
        $manager->assignRole('partner-manager');

        $this->info("Demo ready. Storefront company id: {$store->id}");
        $this->line("admin@test.com / shop@test.com — password: {$password}");

        return self::SUCCESS;
    }
}
