<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Industry;
use App\Models\Partner;
use App\Models\Product;
use App\Models\ProductPriceRule;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Automotive\Models\VehiclePart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** The public side: anyone can browse a store, nobody learns anything private. */
class StorefrontTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private Company $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
        $this->store = $this->customer->company;
        $this->store->update(['status' => 'active', 'settings' => ['storefront' => [
            'enabled' => true, 'headline' => 'Protect your ride', 'subheadline' => 'Film, coating and tint.',
            'primary_color' => '#0a7d5a', 'logo_url' => 'https://cdn.example.com/logo.png',
            'contact_email' => 'hello@store.example', 'featured_product_ids' => [],
        ]]]);
    }

    private function owner(): User
    {
        $owner = User::factory()->create(['company_id' => $this->store->id]);
        $owner->assignRole('company-owner');

        return $owner;
    }

    // ------------------------------------------------------------------ Public API

    public function test_companies_get_a_unique_url_safe_address(): void
    {
        $tenant = Tenant::find($this->store->tenant_id);
        $a = Company::create(['tenant_id' => $tenant->id, 'name' => 'Ahmed & Sons — Auto Care!']);
        $b = Company::create(['tenant_id' => $tenant->id, 'name' => 'Ahmed & Sons — Auto Care!']);
        $arabic = Company::create(['tenant_id' => $tenant->id, 'name' => 'متجر الحماية']);
        $reserved = Company::create(['tenant_id' => $tenant->id, 'name' => 'Admin']);

        $this->assertSame('ahmed-sons-auto-care', $a->slug);
        $this->assertSame('ahmed-sons-auto-care-2', $b->slug);
        $this->assertNotEmpty($arabic->slug);
        $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $arabic->slug);
        $this->assertSame('admin-2', $reserved->slug); // never collides with the site's own addresses
    }

    public function test_anyone_can_browse_an_open_store_without_logging_in(): void
    {
        $this->getJson("/api/public/storefronts/{$this->store->slug}")->assertOk()
            ->assertJsonPath('store.name', $this->store->name)
            ->assertJsonPath('store.headline', 'Protect your ride')
            ->assertJsonPath('store.primary_color', '#0a7d5a')
            ->assertJsonPath('industry.key', 'automotive')
            ->assertJsonPath('featured.0.name', 'Film')
            ->assertJsonPath('featured.0.from_price', 1000)   // the cheapest option, not the base price
            ->assertJsonPath('featured.0.currency', 'USD')
            ->assertJsonPath('partners.count', 1)   // only partners that belong to a company of this tenant
            ->assertJsonPath('partners.cities.0', 'Riyadh');

        $this->getJson('/api/public/storefronts')->assertOk()
            ->assertJsonPath('stores.0.slug', $this->store->slug);
    }

    public function test_closed_missing_and_suspended_stores_are_not_found(): void
    {
        $this->getJson('/api/public/storefronts/no-such-store')->assertNotFound();

        $this->store->update(['status' => 'suspended']);
        $this->getJson("/api/public/storefronts/{$this->store->slug}")->assertNotFound();
        $this->getJson("/api/public/storefronts/{$this->store->slug}/products")->assertNotFound();
        $this->getJson('/api/public/storefronts')->assertJsonCount(0, 'stores');

        $this->store->update(['status' => 'active', 'settings' => ['storefront' => ['enabled' => false]]]);
        $this->getJson("/api/public/storefronts/{$this->store->slug}")->assertNotFound();
        $this->getJson('/api/public/storefronts')->assertJsonCount(0, 'stores');
    }

    public function test_the_public_catalog_shows_only_what_is_for_sale_in_this_stores_industry(): void
    {
        $devices = Industry::where('key', 'devices')->first();
        Product::create(['industry_id' => $devices->id, 'name' => 'Phone Guard', 'slug' => 'pg', 'base_price' => 5, 'is_active' => true]);
        Product::create(['industry_id' => $this->product->industry_id, 'name' => 'Draft', 'slug' => 'draft', 'base_price' => 5, 'is_active' => false]);
        Product::create(['industry_id' => null, 'name' => 'Gift Card', 'slug' => 'gc', 'base_price' => 25, 'brand' => 'Anyone', 'category' => 'gifts']);

        $names = fn ($q = '') => collect($this->getJson("/api/public/storefronts/{$this->store->slug}/products{$q}")->assertOk()->json('data'))->pluck('name')->all();

        $this->assertSame(['Film', 'Gift Card'], $names());
        $this->assertSame(['Gift Card'], $names('?q=gift'));
        $this->assertSame(['Gift Card'], $names('?q=anyone'));
        $this->assertSame(['Gift Card'], $names('?category=gifts'));
        $this->assertSame([], $names('?q=phone'));
        $this->assertSame([], $names('?q=%25')); // a literal wildcard matches nothing, not everything

        // Hidden or foreign products cannot be reached by guessing ids.
        foreach (Product::whereIn('slug', ['pg', 'draft'])->pluck('id') as $id) {
            $this->getJson("/api/public/storefronts/{$this->store->slug}/products/{$id}")->assertNotFound();
        }
    }

    public function test_a_product_page_lists_its_public_prices(): void
    {
        $roof = VehiclePart::create(['name' => 'Roof', 'slug' => 'roof']);
        ProductPriceRule::create(['product_id' => $this->product->id, 'target_type' => 'vehicle_part', 'target_id' => $roof->id, 'price' => 700, 'duration_minutes' => 90]);
        // An inactive rule is not a public price.
        $mirror = VehiclePart::create(['name' => 'Mirror', 'slug' => 'mirror']);
        ProductPriceRule::create(['product_id' => $this->product->id, 'target_type' => 'vehicle_part', 'target_id' => $mirror->id, 'price' => 50, 'is_active' => false]);

        $page = $this->getJson("/api/public/storefronts/{$this->store->slug}/products/{$this->product->id}")->assertOk()
            ->assertJsonPath('product.from_price', 700)
            ->assertJsonPath('product.warranty_years', 5)
            ->assertJsonPath('store.slug', $this->store->slug);

        $this->assertSame([['Roof', 700, 90], ['Hood', 1000, null]], collect($page->json('product.prices'))->map(fn ($p) => [$p['target'], $p['price'], $p['duration_minutes']])->all());
    }

    public function test_nothing_private_ever_appears_in_public_responses(): void
    {
        $this->customer->update(['email' => 'private.customer@example.org']);
        $this->order(false);

        $bodies = [
            $this->getJson("/api/public/storefronts/{$this->store->slug}")->getContent(),
            $this->getJson("/api/public/storefronts/{$this->store->slug}/products")->getContent(),
            $this->getJson("/api/public/storefronts/{$this->store->slug}/products/{$this->product->id}")->getContent(),
            $this->getJson('/api/public/storefronts')->getContent(),
        ];

        foreach ($bodies as $body) {
            foreach (['private.customer@example.org', $this->manager->email, 'latitude', 'longitude', '"phone"', 'commission', 'password', 'company_id', 'tenant_id',
                'legal_name', 'uuid', 'base_price', 'is_active', 'industry_id', 'settings', 'orders', 'partner_id'] as $secret) {
                $this->assertStringNotContainsString($secret, $body, "public response leaked '{$secret}'");
            }
        }

        // Partners appear only as counts and city names.
        $this->assertStringNotContainsString('Rival', $bodies[0]);
        $this->assertStringNotContainsString('Shop', str_replace($this->store->name, '', $bodies[0]));
    }

    public function test_public_responses_are_cacheable_and_rate_limited(): void
    {
        $response = $this->getJson("/api/public/storefronts/{$this->store->slug}")->assertOk();
        $this->assertStringContainsString('public', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=60', $response->headers->get('Cache-Control'));

        // Everything else stays uncacheable.
        $this->actingAs($this->customer, 'sanctum')->getJson('/api/me')->assertHeader('Cache-Control', 'no-store, private');

        config(['platform.public_rate_limit' => 120]);
        foreach (range(1, 120) as $_) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->getJson('/api/public/storefronts');
        }
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->getJson('/api/public/storefronts')->assertStatus(429);
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])->getJson('/api/public/storefronts')->assertOk();
    }

    public function test_public_listing_inputs_are_validated(): void
    {
        $base = "/api/public/storefronts/{$this->store->slug}/products";
        $this->getJson("{$base}?q=".str_repeat('a', 200))->assertUnprocessable();
        $this->getJson("{$base}?page=0")->assertUnprocessable();
        $this->getJson("{$base}?page=abc")->assertUnprocessable();
        $this->getJson("/api/public/storefronts/{$this->store->slug}/products/abc")->assertNotFound();
        $this->getJson("/api/public/storefronts/{$this->store->slug}/products/999999")->assertNotFound();
    }

    // ------------------------------------------------------------------ Editor

    public function test_an_owner_edits_their_own_storefront_and_visitors_see_it(): void
    {
        $this->actingAs($this->owner(), 'sanctum');

        $this->putJson("/api/companies/{$this->store->id}/storefront", [
            'headline' => 'Fresh headline', 'primary_color' => '#112233', 'about' => 'We care.', 'slug' => 'shiny-store',
            'logo_url' => 'https://cdn.example.com/new.png', 'featured_product_ids' => [$this->product->id],
        ])->assertOk()->assertJsonPath('slug', 'shiny-store')->assertJsonPath('path', '/s/shiny-store');

        $this->getJson("/api/companies/{$this->store->id}/storefront")->assertOk()->assertJsonPath('headline', 'Fresh headline');
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/public/storefronts/shiny-store')->assertOk()
            ->assertJsonPath('store.headline', 'Fresh headline')->assertJsonPath('store.primary_color', '#112233')
            ->assertJsonPath('store.about', 'We care.')->assertJsonPath('featured.0.id', $this->product->id);
        $this->getJson("/api/public/storefronts/{$this->store->slug}")->assertNotFound(); // the old address is gone
    }

    public function test_storefront_input_cannot_smuggle_markup_or_bad_urls(): void
    {
        $this->actingAs($this->owner(), 'sanctum');
        $put = fn ($data) => $this->putJson("/api/companies/{$this->store->id}/storefront", $data);

        // The colour goes into a style attribute: only a plain hex colour passes.
        foreach (['red', '#12345', 'url(javascript:alert(1))', '#fff; background:url(//evil)', '#GGGGGG'] as $bad) {
            $put(['primary_color' => $bad])->assertUnprocessable()->assertJsonValidationErrors('primary_color');
        }
        // Logos are fetched by every visitor's browser: https only.
        foreach (['http://cdn.example.com/a.png', 'javascript:alert(1)', 'data:image/png;base64,AAAA', 'ftp://x/y.png', 'not a url'] as $bad) {
            $put(['logo_url' => $bad])->assertUnprocessable()->assertJsonValidationErrors('logo_url');
            $put(['hero_image_url' => $bad])->assertUnprocessable()->assertJsonValidationErrors('hero_image_url');
        }
        // Slugs: lowercase words, not reserved, not taken.
        foreach (['Bad Slug', 'ab', 'x_y', '-bad-', 'api', 'admin', '../etc', 'a'.str_repeat('b', 80)] as $bad) {
            $put(['slug' => $bad])->assertUnprocessable()->assertJsonValidationErrors('slug');
        }
        $other = Company::create(['tenant_id' => $this->store->tenant_id, 'name' => 'Other']);
        $put(['slug' => $other->slug])->assertUnprocessable();

        $put(['headline' => str_repeat('x', 121)])->assertUnprocessable();
        $put(['contact_email' => 'nope'])->assertUnprocessable();

        // Markup in text is stored as text; the site escapes it when drawing. Nothing is executed server-side.
        $put(['headline' => '<script>alert(1)</script>'])->assertOk()->assertJsonPath('headline', '<script>alert(1)</script>');
    }

    public function test_featured_products_must_be_available_in_the_store(): void
    {
        $devices = Industry::where('key', 'devices')->first();
        $foreign = Product::create(['industry_id' => $devices->id, 'name' => 'Phone Guard', 'slug' => 'pg', 'base_price' => 5]);
        $hidden = Product::create(['industry_id' => null, 'name' => 'Draft', 'slug' => 'dr', 'base_price' => 5, 'is_active' => false]);
        $this->actingAs($this->owner(), 'sanctum');

        $this->putJson("/api/companies/{$this->store->id}/storefront", ['featured_product_ids' => [$foreign->id]])->assertUnprocessable();
        $this->putJson("/api/companies/{$this->store->id}/storefront", ['featured_product_ids' => [$hidden->id]])->assertUnprocessable();
        $this->putJson("/api/companies/{$this->store->id}/storefront", ['featured_product_ids' => range(1, 9)])->assertUnprocessable();
        $this->putJson("/api/companies/{$this->store->id}/storefront", ['featured_product_ids' => [$this->product->id]])->assertOk();
    }

    public function test_only_the_companys_own_owner_or_an_admin_can_edit_a_storefront(): void
    {
        $otherCompany = Company::create(['tenant_id' => $this->store->tenant_id, 'name' => 'Rival Store']);
        $rival = User::factory()->create(['company_id' => $otherCompany->id]);
        $rival->assignRole('company-owner');

        $this->actingAs($rival, 'sanctum')->putJson("/api/companies/{$this->store->id}/storefront", ['headline' => 'Hacked'])->assertNotFound();
        $this->getJson("/api/companies/{$this->store->id}/storefront")->assertNotFound();

        // A customer of this store lacks the permission; staff of other companies cannot even see it.
        $this->actingAs($this->customer, 'sanctum')->putJson("/api/companies/{$this->store->id}/storefront", ['headline' => 'Hacked'])->assertForbidden();
        foreach ([$this->manager, $this->tech] as $user) {
            $this->actingAs($user, 'sanctum')->putJson("/api/companies/{$this->store->id}/storefront", ['headline' => 'Hacked'])->assertNotFound();
        }
        $this->app['auth']->forgetGuards();
        $this->putJson("/api/companies/{$this->store->id}/storefront", ['headline' => 'Hacked'])->assertUnauthorized();

        $this->actingAs($this->admin, 'sanctum')->putJson("/api/companies/{$this->store->id}/storefront", ['headline' => 'By admin'])->assertOk();
        $this->getJson("/api/companies/{$this->store->id}/storefront")->assertJsonPath('headline', 'By admin');
    }

    // ------------------------------------------------------------------ Signup from a store

    private function signupVia(array $over)
    {
        $challenge = $this->getJson('/api/signup-challenge')->json();
        $this->travel(5)->seconds();

        return $this->postJson('/api/register', $over + [
            'name' => 'Visitor', 'email' => uniqid('v').'@example.com', 'signup_token' => $challenge['token'],
            'password' => 'password123', 'password_confirmation' => 'password123',
        ]);
    }

    public function test_visitors_sign_up_through_a_store_link_without_knowing_a_company_id(): void
    {
        $this->signupVia(['store' => $this->store->slug])->assertCreated();

        $user = User::where('name', 'Visitor')->first();
        $this->assertSame($this->store->id, $user->company_id);
        $this->assertTrue($user->hasRole('customer'));

        $this->signupVia(['store' => 'no-such-store'])->assertUnprocessable()->assertJsonValidationErrors('store');
        $this->signupVia([])->assertUnprocessable(); // a store or a company is required

        // A closed storefront cannot be used to sign up either.
        $this->store->update(['settings' => ['storefront' => ['enabled' => false]]]);
        $this->signupVia(['store' => $this->store->slug])->assertUnprocessable()->assertJsonValidationErrors('store');
    }

    public function test_a_product_page_carries_a_gallery_a_was_price_highlights_and_related_products(): void
    {
        $this->product->update([
            'images' => ['https://cdn.example.com/a.jpg', '/media/'.str_repeat('a', 8).'-aaaa-aaaa-aaaa-'.str_repeat('a', 12).'.png'],
            'compare_at_price' => 9999, 'attributes' => ['highlights' => ['Fast', 'Warranted']],
        ]);
        $other = Product::create(['industry_id' => $this->product->industry_id, 'name' => 'Another', 'slug' => 'another-one', 'base_price' => 10, 'is_active' => true, 'category' => $this->product->category]);

        $page = $this->getJson("/api/public/storefronts/{$this->store->slug}/products/{$this->product->id}")->assertOk();
        $this->assertCount(2, $page->json('product.images'));
        $this->assertSame(9999, (int) $page->json('product.compare_at_price'));
        $this->assertSame(['Fast', 'Warranted'], $page->json('product.highlights'));
        $this->assertContains($other->id, array_column($page->json('related'), 'id'));
        $this->assertNotContains($this->product->id, array_column($page->json('related'), 'id'));
    }

    public function test_product_images_must_be_https_or_uploaded_and_never_script(): void
    {
        $this->actingAs($this->admin, 'sanctum');
        $base = ['name' => 'Imaged', 'slug' => 'imaged', 'base_price' => 5, 'category' => 'misc'];

        foreach (['javascript:alert(1)', 'http://insecure.example/a.png', '/media/../../etc/passwd', 'data:image/svg+xml;base64,AAAA'] as $bad) {
            $this->postJson('/api/products', $base + ['images' => [$bad]])->assertStatus(422);
        }
        $this->postJson('/api/products', $base + ['images' => array_fill(0, 9, 'https://cdn.example.com/a.jpg')])->assertStatus(422);
        $this->postJson('/api/products', $base + ['images' => ['https://cdn.example.com/a.jpg'], 'compare_at_price' => 12, 'attributes' => ['highlights' => ['One']]])->assertCreated();
    }
}
