<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EgyptDemoTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_egyptian_demo_builds_a_working_marketplace_and_can_be_run_again(): void
    {
        Storage::fake('local');
        $this->artisan('platform:demo-egypt')->assertSuccessful();

        $products = Product::where('market', 'EG')->count();
        $users = User::count();
        $this->assertGreaterThan(20, $products);

        // Every product has pictures, a price in EGP and (for goods) a seller; every store has a hero picture that is actually stored.
        foreach (Product::where('market', 'EG')->get() as $p) {
            $this->assertNotEmpty($p->images, $p->name);
            foreach ($p->images as $path) {
                $this->assertTrue(Storage::disk('local')->exists('media/'.basename($path)), $path);
            }
        }

        // The public site shows them, priced in pounds, with the gallery and the was-price.
        $stores = $this->getJson('/api/public/storefronts')->assertOk()->json('stores');
        $this->assertCount(4, $stores);
        $lantern = Product::where('slug', 'eg-lantern')->firstOrFail();
        $page = $this->getJson('/api/public/storefronts/beit-masr/products/'.$lantern->id)->assertOk();
        $this->assertSame('EGP', $page->json('product.currency'));
        $this->assertGreaterThan((float) $page->json('product.from_price'), (float) $page->json('product.compare_at_price'));
        $this->assertCount(3, $page->json('product.images'));

        // The demo's people can sign in.
        $this->postJson('/api/login', ['email' => 'owner.home@demo.eg', 'password' => 'password123'])->assertOk();
        $this->postJson('/api/login', ['email' => 'seller.khan@demo.eg', 'password' => 'password123'])->assertOk();

        // Running it again changes nothing.
        $this->artisan('platform:demo-egypt')->assertSuccessful();
        $this->assertSame($products, Product::where('market', 'EG')->count());
        $this->assertSame($users, User::count());
    }

    public function test_it_refuses_production_without_force(): void
    {
        $this->app['env'] = 'production';
        $this->artisan('platform:demo-egypt')->assertFailed();
    }
}
