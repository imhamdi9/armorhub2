<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Database\Seeders\IndustrySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductCategoryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed([IndustrySeeder::class, RolesAndPermissionsSeeder::class]);
        $u = User::factory()->create();
        $u->assignRole('platform-admin');
        Sanctum::actingAs($u);

        return $u;
    }

    public function test_a_product_needs_a_category_and_it_is_stored_in_one_spelling(): void
    {
        $this->admin();
        $base = ['name' => 'Film', 'slug' => 'film', 'base_price' => 10];

        $this->postJson('/api/products', $base)->assertStatus(422)->assertJsonValidationErrors('category');
        $this->postJson('/api/products', $base + ['category' => '   '])->assertStatus(422);

        $id = $this->postJson('/api/products', $base + ['category' => 'Skin Care'])->assertCreated()->json('id');
        $this->assertSame('skin-care', Product::find($id)->category);

        $this->putJson("/api/products/{$id}", ['category' => ''])->assertStatus(422);
        $this->putJson("/api/products/{$id}", ['category' => 'Hair Care'])->assertOk();
        $this->assertSame('hair-care', Product::find($id)->category);
    }

    public function test_the_category_list_has_each_category_once(): void
    {
        $this->admin();
        foreach ([['a', 'ppf'], ['b', 'ppf'], ['c', 'tint']] as [$n, $c]) {
            Product::create(['name' => $n, 'slug' => $n, 'base_price' => 1, 'is_active' => true, 'category' => $c]);
        }
        Product::create(['name' => 'd', 'slug' => 'd', 'base_price' => 1, 'is_active' => true]);

        $this->getJson('/api/products/categories')->assertOk()->assertExactJson(['ppf', 'tint']);
    }

    public function test_installation_can_be_set_for_many_products_at_once_within_what_a_person_may_change(): void
    {
        $this->admin();
        $ids = [];
        foreach (['a', 'b', 'c'] as $n) {
            $ids[] = Product::create(['name' => $n, 'slug' => $n, 'base_price' => 1, 'is_active' => true, 'category' => 'x'])->id;
        }

        $this->postJson('/api/products/bulk-installation', ['ids' => $ids, 'installation' => 'bogus'])->assertStatus(422);
        $this->postJson('/api/products/bulk-installation', ['ids' => [], 'installation' => 'optional'])->assertStatus(422);
        $this->postJson('/api/products/bulk-installation', ['ids' => $ids, 'installation' => 'optional'])->assertOk()->assertJson(['updated' => 3, 'skipped' => 0]);
        $this->assertSame(['optional'], Product::whereIn('id', $ids)->pluck('installation')->unique()->values()->all());

        // A seller can only change its own products; the rest are reported as skipped.
        $seller = User::factory()->create();
        $seller->assignRole('seller');
        $tenant = \App\Models\Tenant::create(['name' => 'T', 'industry_id' => \App\Models\Industry::first()->id]);
        $company = \App\Models\Company::create(['tenant_id' => $tenant->id, 'name' => 'Seller Co', 'type' => 'supplier', 'status' => 'active']);
        $seller->update(['company_id' => $company->id]);
        $own = Product::create(['name' => 'own', 'slug' => 'own', 'base_price' => 1, 'is_active' => true, 'category' => 'x', 'seller_id' => $company->id]);
        Sanctum::actingAs($seller);
        $this->postJson('/api/products/bulk-installation', ['ids' => [...$ids, $own->id], 'installation' => 'required'])->assertOk()->assertJson(['updated' => 1, 'skipped' => 3]);
        $this->assertSame('required', $own->fresh()->installation);
        $this->assertSame(['optional'], Product::whereIn('id', $ids)->pluck('installation')->unique()->values()->all());
    }
}
