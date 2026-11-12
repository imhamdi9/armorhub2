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
}
