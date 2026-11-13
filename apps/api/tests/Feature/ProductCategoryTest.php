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

    public function test_products_use_the_platform_taxonomy_and_admins_extend_it(): void
    {
        $this->admin();
        $base = ['name' => 'Film', 'slug' => 'film', 'base_price' => 10];

        // Only a category of the taxonomy is accepted; nobody types a new one in a product form.
        $this->postJson('/api/products', $base + ['category' => 'made-up'])->assertStatus(422)->assertJsonValidationErrors('category');

        $tree = $this->getJson('/api/products/categories')->assertOk()->json();
        $auto = collect($tree)->firstWhere('key', 'automotive');
        $this->assertContains('ppf', array_column($auto['children'], 'key'));
        $this->assertNotEmpty($auto['name_ar']);

        // An admin adds one (English and Arabic names), then it can be used; a used category cannot be hidden.
        $parent = \App\Models\ProductCategory::where('key', 'automotive')->value('id');
        $id = $this->postJson('/api/admin/categories', ['key' => 'tyres', 'name_en' => 'Tyres', 'name_ar' => 'الإطارات', 'parent_id' => $parent])->assertCreated()->json('id');
        $this->postJson('/api/admin/categories', ['key' => 'tyres', 'name_en' => 'x', 'name_ar' => 'x'])->assertStatus(422);
        $this->postJson('/api/products', $base + ['category' => 'tyres'])->assertCreated();
        $this->putJson("/api/admin/categories/{$id}", ['is_active' => false])->assertStatus(422)->assertJsonValidationErrors('is_active');
        $kids = collect(collect($this->getJson('/api/admin/categories')->json())->firstWhere('key', 'automotive')['children']);
        $this->assertSame(1, $kids->firstWhere('key', 'tyres')['products']);
    }

    public function test_every_product_has_a_sku_and_carton_data_is_validated(): void
    {
        $this->admin();
        $base = ['name' => 'Film', 'slug' => 'film', 'base_price' => 10, 'category' => 'ppf', 'market' => 'EG'];

        $made = $this->postJson('/api/products', $base)->assertCreated()->json();
        $this->assertMatchesRegularExpression('/^AH-EG-\d{6}$/', $made['sku']);

        $own = $this->postJson('/api/products', ['slug' => 'f2', 'sku' => 'my-film-01'] + $base)->assertCreated()->json();
        $this->assertSame('MY-FILM-01', $own['sku']);
        $this->postJson('/api/products', ['slug' => 'f3', 'sku' => 'MY-FILM-01'] + $base)->assertStatus(422)->assertJsonValidationErrors('sku');
        $this->postJson('/api/products', ['slug' => 'f4', 'sku' => 'bad sku!'] + $base)->assertStatus(422)->assertJsonValidationErrors('sku');

        $carton = ['units_per_carton' => 12, 'carton_length_cm' => 40, 'carton_width_cm' => 30, 'carton_height_cm' => 25, 'carton_weight_kg' => 6.5, 'stock_cartons' => 10];
        $this->putJson("/api/products/{$made['id']}", $carton)->assertOk()->assertJson(['units_per_carton' => 12, 'stock_cartons' => 10]);
        $this->assertSame(120, Product::find($made['id'])->stockUnits());
        $this->putJson("/api/products/{$made['id']}", ['units_per_carton' => 0])->assertStatus(422);
        $this->putJson("/api/products/{$made['id']}", ['carton_weight_kg' => -1])->assertStatus(422);
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

    public function test_the_platform_admin_builds_any_depth_renames_and_moves_categories(): void
    {
        $this->admin();
        $auto = \App\Models\ProductCategory::where('key', 'automotive')->value('id');

        // A new top-level group, a category under an existing one, and a sub-category under THAT one (the key is made from the name).
        $top = $this->postJson('/api/admin/categories', ['name_en' => 'Marine', 'name_ar' => 'بحري'])->assertCreated()->json();
        $this->assertSame('marine', $top['key']);
        $wipers = $this->postJson('/api/admin/categories', ['name_en' => 'Wipers', 'name_ar' => 'مساحات', 'parent_id' => $auto])->assertCreated()->json();
        $blades = $this->postJson('/api/admin/categories', ['name_en' => 'Blades', 'name_ar' => 'شفرات', 'parent_id' => $wipers['id']])->assertCreated()->json();
        $same = $this->postJson('/api/admin/categories', ['name_en' => 'Blades', 'name_ar' => 'شفرات', 'parent_id' => $wipers['id']])->assertCreated()->json();
        $this->assertSame('blades-2', $same['key']);

        // Products can be filed under the deepest level, and the public tree nests all levels.
        $this->postJson('/api/products', ['name' => 'Blade', 'slug' => 'blade', 'base_price' => 5, 'category' => 'blades'])->assertCreated();
        $tree = collect($this->getJson('/api/products/categories')->assertOk()->json())->firstWhere('key', 'automotive');
        $w = collect($tree['children'])->firstWhere('key', 'wipers');
        $this->assertSame(['blades', 'blades-2'], array_column($w['children'], 'key'));

        // Rename (English and Arabic) keeps the key; moving changes the place; a category cannot go under its own branch.
        $this->putJson("/api/admin/categories/{$wipers['id']}", ['name_en' => 'Wiper systems', 'name_ar' => 'أنظمة المساحات'])->assertOk()->assertJsonPath('name_en', 'Wiper systems')->assertJsonPath('key', 'wipers');
        $this->putJson("/api/admin/categories/{$wipers['id']}", ['parent_id' => $blades['id']])->assertStatus(422)->assertJsonValidationErrors('parent_id');
        $this->putJson("/api/admin/categories/{$wipers['id']}", ['parent_id' => $top['id']])->assertOk()->assertJsonPath('parent_id', $top['id']);
        $this->putJson("/api/admin/categories/{$wipers['id']}", ['parent_id' => null])->assertOk()->assertJsonPath('parent_id', null);

        $admin = collect($this->getJson('/api/admin/categories')->assertOk()->json());
        $this->assertNotNull($admin->firstWhere('key', 'wipers'));                              // now a top-level group of its own
        $this->assertSame(2, count($admin->firstWhere('key', 'wipers')['children']));
    }
}
