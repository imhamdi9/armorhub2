<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** Things the full click-through audit found: screens that offered actions the server would refuse. */
class LaunchFixesTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
    }

    public function test_a_product_says_whether_the_signed_in_person_may_edit_it(): void
    {
        $product = Product::first();

        $this->actingAs($this->admin, 'sanctum')->getJson("/api/products/{$product->id}")->assertOk()->assertJsonPath('can_edit', true);
        $this->actingAs($this->customer, 'sanctum')->getJson("/api/products/{$product->id}")->assertOk()->assertJsonPath('can_edit', false);
        $this->actingAs($this->manager, 'sanctum')->getJson("/api/products/{$product->id}")->assertOk()->assertJsonPath('can_edit', false);
    }

    public function test_a_store_owner_sees_a_sellers_product_but_is_told_they_cannot_change_it(): void
    {
        $tenantId = $this->customer->company->tenant_id;
        $sellerCo = Company::create(['tenant_id' => $tenantId, 'name' => 'Gulf Parts', 'type' => 'supplier', 'status' => 'active']);
        $theirs = Product::create(['name' => 'Seller thing', 'slug' => 'seller-thing', 'base_price' => 5, 'is_active' => true, 'seller_id' => $sellerCo->id, 'industry_id' => Product::first()->industry_id]);
        $owner = User::factory()->create(['company_id' => $this->customer->company_id]);
        $owner->assignRole('company-owner');

        $this->actingAs($owner, 'sanctum')->getJson("/api/products/{$theirs->id}")->assertOk()->assertJsonPath('can_edit', false)->assertJsonPath('seller_id', $sellerCo->id);
    }

    public function test_admins_can_manage_sellers_and_the_list_says_who_is_manageable(): void
    {
        $sellerCo = Company::create(['tenant_id' => $this->customer->company->tenant_id, 'name' => 'Gulf Parts', 'type' => 'supplier', 'status' => 'active']);
        $seller = User::factory()->create(['company_id' => $sellerCo->id]);
        $seller->assignRole('seller');

        $this->actingAs($this->admin, 'sanctum')->putJson("/api/users/{$seller->id}", ['is_active' => false])->assertOk()->assertJsonPath('manageable', true);

        $rows = collect($this->getJson('/api/users')->assertOk()->json('data'))->keyBy('id');
        $this->assertFalse($rows[$this->admin->id]['manageable']);        // not yourself
        $this->assertTrue($rows[$seller->id]['manageable']);
    }

    public function test_an_owner_is_not_offered_actions_on_people_they_cannot_manage(): void
    {
        $owner = User::factory()->create(['company_id' => $this->customer->company_id]);
        $owner->assignRole('company-owner');

        $rows = collect($this->actingAs($owner, 'sanctum')->getJson('/api/users')->assertOk()->json('data'))->keyBy('id');
        $this->assertFalse($rows[$this->customer->id]['manageable']);     // customers are not for owners to manage
        $this->putJson("/api/users/{$this->customer->id}", ['is_active' => false])->assertForbidden();
    }

    public function test_only_customer_owned_subjects_are_listed_as_things_a_customer_keeps(): void
    {
        $industry = $this->actingAs($this->customer, 'sanctum')->getJson('/api/me')->assertOk()->json('industry');
        $this->assertArrayHasKey('owned_subjects', $industry);
        $this->assertSame([], array_values(array_intersect($industry['owned_subjects'], ['vehicle_model'])));   // a shared catalog, not "my vehicle models"
    }

    public function test_a_partner_account_without_a_partner_gets_a_helpful_message(): void
    {
        $this->actingAs($this->manager, 'sanctum');
        $this->manager->forceFill(['partner_id' => null])->save();

        $this->getJson('/api/partner/orders')->assertStatus(422)->assertJsonPath('message', 'Your account is not linked to a partner yet. Ask your company owner to assign you to one.');
    }
}
