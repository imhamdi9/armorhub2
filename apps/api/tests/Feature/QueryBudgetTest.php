<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** Pages that list products must cost the same number of queries for 3 products as for 30: no query per card. */
class QueryBudgetTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private function queries(string $url): int
    {
        $this->getJson($url)->assertOk();                                                                  // warm-up: roles, settings and the like are cached after the first request
        Cache::flush();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson($url)->assertOk();
        $n = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $n;
    }

    private function addProducts(int $count, ?int $sellerId = null): void
    {
        static $serial = 0;
        for ($i = 0; $i < $count; $i++) {
            $serial++;
            Product::create(['name' => "Item {$serial}", 'slug' => "item-{$serial}", 'base_price' => 10 + $serial, 'is_active' => true, 'category' => 'x', 'seller_id' => $sellerId, 'requires_shipping' => true,
                'industry_id' => $this->product->industry_id, 'fitment_required' => $serial % 2 === 0]);
        }
    }

    public function test_the_public_catalogue_and_product_page_do_not_query_once_per_card(): void
    {
        $this->buildMarketplace();
        $store = $this->customer->company;
        $store->update(['status' => 'active', 'settings' => ['storefront' => ['enabled' => true]]]);
        $co = Company::create(['tenant_id' => $store->tenant_id, 'name' => 'Gulf Parts', 'type' => 'supplier', 'status' => 'active']);
        $list = "/api/public/storefronts/{$store->slug}/products";
        $page = fn () => "/api/public/storefronts/{$store->slug}/products/{$this->product->id}";

        $this->addProducts(3, $co->id);
        [$listSmall, $pageSmall, $homeSmall] = [$this->queries($list), $this->queries($page()), $this->queries("/api/public/storefronts/{$store->slug}")];
        $this->addProducts(9, $co->id);
        [$listBig, $pageBig, $homeBig] = [$this->queries($list), $this->queries($page()), $this->queries("/api/public/storefronts/{$store->slug}")];

        $this->assertLessThanOrEqual($listSmall + 2, $listBig, 'the catalogue list grows with the number of products');       // nine more products would add eighteen queries if each card asked its own
        $this->assertLessThanOrEqual($pageSmall + 2, $pageBig, 'the product page grows with the number of related products');
        $this->assertLessThanOrEqual($homeSmall + 2, $homeBig, 'the store home grows with the number of products');
        $this->assertLessThanOrEqual(12, $listBig);
        $this->assertLessThanOrEqual(20, $pageBig);
    }

    public function test_a_sellers_product_list_with_its_readiness_scores_does_not_query_once_per_product(): void
    {
        $this->buildMarketplace();
        $store = $this->customer->company;
        $co = Company::create(['tenant_id' => $store->tenant_id, 'name' => 'Gulf Parts', 'type' => 'supplier', 'status' => 'active']);
        $seller = User::factory()->create(['email' => 's@x.example.org', 'company_id' => $co->id]);
        $seller->assignRole('seller');
        $this->actingAs($seller, 'sanctum');

        $this->addProducts(3, $co->id);
        $small = $this->queries('/api/products');
        $this->addProducts(12, $co->id);
        $big = $this->queries('/api/products');

        $this->assertLessThanOrEqual($small + 2, $big, 'the product list grows with the number of products');
        $this->assertSame(15, count($this->getJson('/api/products')->json()));
    }

    public function test_a_customers_order_list_does_not_query_once_per_order(): void
    {
        $this->buildMarketplace();
        $this->actingAs($this->customer, 'sanctum');
        $make = function (int $n) {
            for ($i = 0; $i < $n; $i++) {
                Order::withoutGlobalScopes()->forceCreate(['company_id' => $this->customer->company_id, 'customer_id' => $this->customer->id, 'product_id' => $this->product->id, 'status' => 'completed',
                    'product_amount' => 1, 'total_amount' => 1, 'currency' => 'EGP']);
            }
        };
        $make(3);
        $small = $this->queries('/api/orders');
        $make(10);
        $this->assertLessThanOrEqual($small + 2, $this->queries('/api/orders'));
    }
}
