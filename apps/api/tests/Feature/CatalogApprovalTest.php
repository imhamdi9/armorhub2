<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Industry;
use App\Models\ProductCategory;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\IndustrySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The platform admin changes catalogs and categories at once; a market admin only proposes, and it goes live when the platform admin approves. */
class CatalogApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $marketAdmin;
    private Tenant $egypt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IndustrySeeder::class, RolesAndPermissionsSeeder::class]);
        app(\App\Modules\IndustryRegistry::class)->boot();
        $this->egypt = Tenant::create(['name' => 'ArmorHub Egypt', 'industry_id' => Industry::first()->id, 'market' => 'EG']);
        $co = Company::create(['tenant_id' => $this->egypt->id, 'name' => 'EG Store', 'type' => 'partner', 'status' => 'active']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('platform-admin');
        $this->marketAdmin = User::factory()->create(['company_id' => $co->id]);
        $this->marketAdmin->assignRole('company-owner');
    }

    public function test_a_market_admins_category_waits_for_the_platform_admin(): void
    {
        $body = ['key' => 'wipers', 'name_en' => 'Wipers', 'name_ar' => 'مساحات', 'parent_id' => ProductCategory::where('key', 'automotive')->value('id')];

        $res = $this->actingAs($this->marketAdmin, 'sanctum')->postJson('/api/admin/categories', $body)->assertStatus(202)->assertJson(['pending' => true]);
        $this->assertFalse(ProductCategory::where('key', 'wipers')->exists());              // not live yet
        $this->getJson('/api/products/categories')->assertOk();
        $mine = $this->getJson('/api/admin/catalog-changes')->assertOk()->assertJsonCount(1)->json();
        $this->assertSame('pending', $mine[0]['status']);

        // The market admin cannot approve their own request.
        $this->postJson("/api/admin/catalog-changes/{$res->json('change_id')}/approve")->assertForbidden();

        // The platform admin sees it and approves: now it is live for everyone, and the proposer is told.
        $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/catalog-changes?status=pending')->assertOk()->assertJsonCount(1)->assertJsonPath('0.market', 'ArmorHub Egypt');
        $this->postJson("/api/admin/catalog-changes/{$res->json('change_id')}/approve")->assertOk()->assertJson(['status' => 'approved']);
        $this->assertTrue(ProductCategory::where('key', 'wipers')->exists());
        $this->postJson("/api/admin/catalog-changes/{$res->json('change_id')}/approve")->assertStatus(422);   // only once
        $this->assertSame(1, $this->marketAdmin->notifications()->where('data->kind', 'notice')->count());

        // Hiding a category is also a proposal; a rejection needs a reason and changes nothing.
        $id = ProductCategory::where('key', 'wipers')->value('id');
        $this->actingAs($this->marketAdmin, 'sanctum')->putJson("/api/admin/categories/{$id}", ['is_active' => false])->assertStatus(202);
        $change = \App\Models\CatalogChange::where('status', 'pending')->first();
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/admin/catalog-changes/{$change->id}/reject", [])->assertUnprocessable();
        $this->postJson("/api/admin/catalog-changes/{$change->id}/reject", ['note' => 'Keep it visible'])->assertOk();
        $this->assertTrue(ProductCategory::find($id)->is_active);
    }

    public function test_a_market_admins_catalog_item_waits_too_and_the_platform_admin_changes_at_once(): void
    {
        $this->actingAs($this->marketAdmin, 'sanctum')->postJson('/api/catalog/room_type', ['name' => 'Garage', 'slug' => 'garage'])->assertStatus(202);
        $this->assertSame(0, \App\Modules\HomeServices\Models\RoomType::where('slug', 'garage')->count());

        $change = \App\Models\CatalogChange::first();
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/admin/catalog-changes/{$change->id}/approve")->assertOk();
        $this->assertSame(1, \App\Modules\HomeServices\Models\RoomType::where('slug', 'garage')->count());

        // The platform admin edits directly, no waiting.
        $id = \App\Modules\HomeServices\Models\RoomType::where('slug', 'garage')->value('id');
        $this->putJson("/api/catalog/room_type/{$id}", ['name' => 'Car Garage'])->assertOk()->assertJsonPath('name', 'Car Garage');
        $this->postJson('/api/admin/categories', ['key' => 'tyres', 'name_en' => 'Tyres', 'name_ar' => 'إطارات'])->assertCreated();

        // A market admin's delete is a proposal as well: nothing is removed until approved.
        $this->actingAs($this->marketAdmin, 'sanctum')->deleteJson("/api/catalog/room_type/{$id}")->assertStatus(202);
        $this->assertSame(1, \App\Modules\HomeServices\Models\RoomType::where('slug', 'garage')->count());
    }
}
