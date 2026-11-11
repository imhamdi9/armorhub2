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

        $admin = User::create(['name' => 'Admin', 'email' => 'admin@test.com', 'password' => $password, 'email_verified_at' => now()]);
        $admin->assignRole('platform-admin');

        $tenant = Tenant::create([
            'name' => 'Armor',
            'industry_id' => Industry::where('key', 'automotive')->value('id'),
            'market' => 'SA',
        ]);
        $store = Company::create([
            'tenant_id' => $tenant->id, 'name' => 'Armor Store', 'status' => 'active',
            // A ready-made public storefront at /s/armor-store.
            'settings' => ['storefront' => [
                'enabled' => true,
                'headline' => 'Protect your car like it matters',
                'subheadline' => 'Paint protection film, ceramic coating and tint, fitted by certified installers near you.',
                'about' => "Armor Store connects car owners with trusted, certified installers.\nGet a price in seconds and book with confidence.",
                'primary_color' => '#0f766e',
                'contact_email' => 'hello@armor-store.example',
                'featured_product_ids' => [],
            ]],
        ]);
        $shopCompany = Company::create(['tenant_id' => $tenant->id, 'name' => 'Shop Co', 'type' => 'partner']);

        $partner = Partner::create([
            'company_id' => $shopCompany->id,
            'name' => 'Shop One', 'city' => 'Riyadh', 'country' => 'SA',
            'latitude' => 24.7, 'longitude' => 46.7, 'location_enabled' => true,
            'public_profile' => true, 'description' => 'Certified paint protection and ceramic coating specialists in central Riyadh.',
        ]);

        $manager = User::create([
            'name' => 'Shop Manager', 'email' => 'shop@test.com', 'password' => $password,
            'company_id' => $shopCompany->id, 'partner_id' => $partner->id, 'email_verified_at' => now(),
        ]);
        $manager->assignRole('partner-manager');

        // A physical product: it needs a delivery address and a shipment after payment.
        $hood = \App\Modules\Automotive\Models\VehiclePart::where('slug', 'hood')->first();
        $kit = \App\Models\Product::create([
            'industry_id' => $tenant->industry_id, 'market' => 'SA', 'category' => 'care-kits', 'brand' => 'Armor', 'name' => 'Ceramic Care Kit',
            'slug' => 'ceramic-care-kit', 'base_price' => 80, 'is_active' => true, 'requires_shipping' => true,
            'description' => 'Maintenance kit for coated paint, delivered to your door.',
        ]);
        if ($hood) {
            \App\Models\ProductPriceRule::create(['product_id' => $kit->id, 'target_type' => 'vehicle_part', 'target_id' => $hood->id, 'price' => 80, 'is_active' => true]);
        }

        $this->seedEgypt($password);

        $this->info("Demo ready. Storefront: /s/{$store->slug} (company id {$store->id})");
        $this->line("admin@test.com / shop@test.com — password: {$password}");

        return self::SUCCESS;
    }

    /** A second country: the same catalog, priced in Egyptian pounds, with its own storefront and partner. */
    private function seedEgypt(string $password): void
    {
        $industry = Industry::where('key', 'automotive')->value('id');
        $saProduct = \App\Models\Product::where('slug', 'xpel-ultimate-plus')->first();
        $saProduct?->update(['market' => 'SA']);

        $egProduct = \App\Models\Product::create([
            'industry_id' => $industry, 'market' => 'EG', 'category' => 'paint-protection-film', 'brand' => 'XPEL',
            'name' => 'Ultimate Plus', 'slug' => 'xpel-ultimate-plus-eg', 'base_price' => 6000, 'warranty_years' => 10, 'is_active' => true,
        ]);
        foreach (\App\Models\ProductPriceRule::where('product_id', $saProduct?->id)->get() as $rule) {
            \App\Models\ProductPriceRule::create([
                'product_id' => $egProduct->id, 'target_type' => $rule->target_type, 'target_id' => $rule->target_id,
                'price' => round($rule->price * 13), 'duration_minutes' => $rule->duration_minutes, 'is_active' => true,
            ]);
        }

        $tenant = Tenant::create(['name' => 'Nile', 'industry_id' => $industry, 'market' => 'EG']);
        Company::create([
            'tenant_id' => $tenant->id, 'name' => 'Nile Shield', 'status' => 'active',
            'settings' => ['storefront' => [
                'enabled' => true,
                'headline' => 'احمِ سيارتك بأفضل الخبراء في مصر',
                'subheadline' => 'أفلام حماية الطلاء والسيراميك والتظليل، يركّبها فنيون معتمدون بالقرب منك.',
                'about' => "نايل شيلد تربط أصحاب السيارات بأفضل مراكز التركيب المعتمدة في القاهرة والإسكندرية.",
                'primary_color' => '#b45309',
                'contact_email' => 'hello@nile-shield.example',
                'featured_product_ids' => [],
            ]],
        ]);
        $partnerCompany = Company::create(['tenant_id' => $tenant->id, 'name' => 'Cairo Garage', 'type' => 'partner']);
        Partner::create([
            'company_id' => $partnerCompany->id, 'name' => 'Cairo Garage', 'city' => 'Cairo', 'country' => 'EG',
            'latitude' => 30.04, 'longitude' => 31.23, 'location_enabled' => true,
            'public_profile' => true, 'description' => 'Specialist film and tint installers serving Cairo and Giza.',
        ]);
    }
}
