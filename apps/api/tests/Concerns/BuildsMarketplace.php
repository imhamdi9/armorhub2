<?php

namespace Tests\Concerns;

use App\Models\Company;
use App\Models\Industry;
use App\Models\Partner;
use App\Models\PartnerQuote;
use App\Models\Product;
use App\Models\ProductPriceRule;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Automotive\Models\VehiclePart;
use Database\Seeders\IndustrySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

/**
 * A small automotive marketplace: a customer, a partner shop with a manager and a
 * technician, a rival partner, an admin, one warranted product with one price rule.
 */
trait BuildsMarketplace
{
    protected User $customer;
    protected User $manager;
    protected User $tech;
    protected User $rival;
    protected User $admin;
    protected Partner $shop;
    protected Product $product;
    protected VehiclePart $hood;

    protected function buildMarketplace(): void
    {
        $this->seed([IndustrySeeder::class, RolesAndPermissionsSeeder::class]);

        $tenant = Tenant::create(['name' => 'T', 'industry_id' => Industry::where('key', 'automotive')->value('id')]);
        $store = Company::create(['tenant_id' => $tenant->id, 'name' => 'Store']);
        $shopCo = Company::create(['tenant_id' => $tenant->id, 'name' => 'ShopCo']);

        $this->shop = Partner::create([
            'company_id' => $shopCo->id, 'name' => 'Shop', 'city' => 'Riyadh', 'country' => 'SA',
            'latitude' => 24.7, 'longitude' => 46.7, 'location_enabled' => true,
        ]);
        $rivalShop = Partner::create(['name' => 'Rival', 'city' => 'Riyadh', 'country' => 'SA']);

        $mk = fn (string $role, $company, $partner = null) => tap(User::factory()->create([
            'company_id' => $company?->id, 'partner_id' => $partner?->id,
            'city' => 'Riyadh', 'country' => 'SA', 'latitude' => 24.71, 'longitude' => 46.68, 'location_enabled' => true,
        ]), fn ($u) => $u->assignRole($role));

        $this->customer = $mk('customer', $store);
        $this->manager = $mk('partner-manager', $shopCo, $this->shop);
        $this->tech = $mk('technician', $shopCo, $this->shop);
        $this->rival = $mk('partner-manager', null, $rivalShop);
        $this->admin = $mk('platform-admin', null);

        $this->product = Product::create([
            'industry_id' => $tenant->industry_id, 'name' => 'Film', 'slug' => 'film', 'base_price' => 1, 'warranty_years' => 5,
        ]);
        $this->hood = VehiclePart::create(['name' => 'Hood', 'slug' => 'hood']);
        ProductPriceRule::create([
            'product_id' => $this->product->id, 'target_type' => 'vehicle_part', 'target_id' => $this->hood->id, 'price' => 1000,
        ]);
    }

    /** Place an order and (optionally) take it all the way to paid. */
    protected function order(bool $paid = true): int
    {
        $id = $this->actingAs($this->customer, 'sanctum')->postJson('/api/orders', [
            'product_id' => $this->product->id, 'target_type' => 'vehicle_part', 'targets' => [$this->hood->id],
        ])->assertCreated()->json('order.id');

        if ($paid) {
            $quote = PartnerQuote::withoutGlobalScopes()->where('order_id', $id)->where('partner_id', $this->shop->id)->first();
            $this->actingAs($this->manager, 'sanctum')->patchJson("/api/partner-quotes/{$quote->id}", ['price' => 200])->assertOk();
            $this->actingAs($this->customer, 'sanctum')->patchJson("/api/partner-quotes/{$quote->id}/accept")->assertOk();
            $this->postJson("/api/orders/{$id}/payments", ['amount' => 1200])->assertCreated();
        }

        return $id;
    }
}
