<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\MediaFile;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** A seller's shop page: what they say, and what shoppers think. */
class SellerProfileTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    public function test_the_profile_is_public_where_it_should_be_and_private_where_it_should_be(): void
    {
        $this->buildMarketplace();
        Cache::flush();
        $store = $this->customer->company;
        $store->update(['status' => 'active', 'settings' => ['storefront' => ['enabled' => true]]]);
        $co = Company::create(['tenant_id' => $store->tenant_id, 'name' => 'Gulf Parts', 'type' => 'supplier', 'status' => 'active']);
        $seller = User::factory()->create(['company_id' => $co->id]);
        $seller->assignRole('seller');
        $product = Product::create(['name' => 'Pads', 'slug' => 'pads-pf', 'seller_id' => $co->id, 'base_price' => 10, 'is_active' => true, 'industry_id' => $this->product->industry_id]);

        $this->actingAs($this->customer, 'sanctum')->getJson('/api/seller/profile')->assertForbidden();
        $this->actingAs($seller, 'sanctum');
        $this->putJson('/api/seller/profile', ['about' => 'Call me on 0501234567'])->assertUnprocessable()->assertJsonValidationErrors('about');   // no way around the platform
        $this->putJson('/api/seller/profile', ['logo' => '/media/'.str_repeat('a', 36).'.png'])->assertUnprocessable()->assertJsonValidationErrors('logo');
        MediaFile::create(['name' => 'b1b1b1b1-b1b1-b1b1-b1b1-b1b1b1b1b1b1.png', 'original_name' => 'l.png', 'mime' => 'image/png', 'size' => 10, 'sha256' => 'x', 'uploaded_by' => $seller->id]);

        $this->putJson('/api/seller/profile', ['about' => '<b>Genuine</b> brake parts since 2010.', 'city' => 'Cairo', 'hours' => 'Sun-Thu 9-5', 'ships_within_days' => 2,
            'logo' => '/media/b1b1b1b1-b1b1-b1b1-b1b1-b1b1b1b1b1b1.png', 'return_address' => ['line1' => '5 Workshop St', 'city' => 'Cairo', 'notes' => 'Gate 3']])->assertOk()->assertJsonPath('profile.about', 'Genuine brake parts since 2010.');

        foreach ([5, 4, 3] as $i => $stars) {
            ProductReview::create(['product_id' => $product->id, 'user_id' => User::factory()->create()->id, 'rating' => $stars, 'status' => 'published']);
        }
        Order::withoutGlobalScopes()->forceCreate(['company_id' => $store->id, 'customer_id' => $this->customer->id, 'product_id' => $product->id, 'status' => 'completed', 'product_amount' => 1, 'total_amount' => 1, 'currency' => 'USD']);

        $page = $this->getJson("/api/public/storefronts/{$store->slug}/sellers/{$co->id}")->assertOk()->json('seller');
        $this->assertSame('Cairo', $page['city']);
        $this->assertSame('Genuine brake parts since 2010.', $page['about']);
        $this->assertSame(2, $page['ships_within_days']);
        $this->assertEquals(['count' => 3, 'average' => 4.0], $page['rating']);
        $this->assertSame(1, $page['delivered_orders']);
        $this->assertStringNotContainsString('Workshop', json_encode($page));                                        // the return address is private
    }
}
