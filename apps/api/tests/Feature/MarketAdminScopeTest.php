<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Industry;
use App\Models\SellerPayout;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\IndustrySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A market admin runs everything of their own market (every company in it) and reaches nothing of another market or the platform. */
class MarketAdminScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_market_admin_reaches_the_whole_market_and_only_it(): void
    {
        $this->seed([IndustrySeeder::class, RolesAndPermissionsSeeder::class]);
        $mk = fn (string $n, string $code) => Tenant::create(['name' => $n, 'industry_id' => Industry::first()->id, 'market' => $code]);
        $eg = $mk('ArmorHub Egypt', 'EG');
        $sa = $mk('ArmorHub Saudi', 'SA');
        $egStore = Company::create(['tenant_id' => $eg->id, 'name' => 'EG Store', 'type' => 'partner', 'status' => 'active']);
        $egSeller = Company::create(['tenant_id' => $eg->id, 'name' => 'EG Seller', 'type' => 'supplier', 'status' => 'active']);
        $saSeller = Company::create(['tenant_id' => $sa->id, 'name' => 'SA Seller', 'type' => 'supplier', 'status' => 'active']);
        $admin = User::factory()->create(['company_id' => $egStore->id]);
        $admin->assignRole('company-owner');
        $this->actingAs($admin, 'sanctum');

        // Companies of the whole market, none of the other.
        $names = collect($this->getJson('/api/companies')->assertOk()->json())->pluck('name');
        $this->assertEqualsCanonicalizing(['EG Store', 'EG Seller'], $names->all());

        // Money screens work, limited to the market: another market's payout does not exist for them.
        $this->getJson('/api/admin/payouts')->assertOk();
        $this->getJson('/api/admin/centre-withdrawals')->assertOk();
        $this->getJson('/api/admin/installation-disputes')->assertOk();
        $this->getJson('/api/admin/audit-logs')->assertOk();
        $foreign = SellerPayout::create(['company_id' => $saSeller->id, 'currency' => 'SAR', 'amount' => 10, 'status' => 'prepared']);
        $this->postJson("/api/admin/payouts/{$foreign->id}/paid", ['reference' => 'x'])->assertNotFound();
        $this->postJson('/api/admin/payouts', ['company_id' => $saSeller->id, 'currency' => 'SAR'])->assertNotFound();

        // Platform-level screens stay closed; they cannot create or delete companies or open markets.
        $this->getJson('/api/admin/settings')->assertForbidden();
        $this->getJson('/api/admin/markets')->assertForbidden();
        $this->postJson('/api/companies', ['tenant_id' => $sa->id, 'name' => 'Sneaky'])->assertForbidden();
    }

    public function test_a_market_admin_opens_departments_for_their_market_and_names_heads_there_only(): void
    {
        $this->seed([IndustrySeeder::class, RolesAndPermissionsSeeder::class]);
        $eg = Tenant::create(['name' => 'ArmorHub Egypt', 'industry_id' => Industry::first()->id, 'market' => 'EG']);
        $store = Company::create(['tenant_id' => $eg->id, 'name' => 'EG Store', 'type' => 'partner', 'status' => 'active']);
        $admin = User::factory()->create(['company_id' => $store->id]);
        $admin->assignRole('company-owner');
        $this->actingAs($admin, 'sanctum');

        $rows = $this->postJson('/api/departments/open')->assertOk()->json();
        $this->assertCount(10, $rows);
        $this->assertSame([$eg->id], collect($rows)->pluck('tenant_id')->unique()->values()->all() ?: [$eg->id]);
        $cs = \App\Models\Department::where('tenant_id', $eg->id)->where('key', 'customer-service')->first();

        // The admin builds a role of that department, invites a head, and the head works inside the market's company.
        $this->postJson("/api/departments/{$cs->id}/roles", ['name' => 'lead', 'permissions' => ['orders.view', 'orders.update']])->assertCreated();
        \Illuminate\Support\Facades\Notification::fake();
        $this->postJson('/api/invitations', ['email' => 'head@egypt.test', 'department_id' => $cs->id, 'as_head' => true, 'role' => "dept-t{$eg->id}-customer-service-lead"])->assertCreated();
        $inv = \App\Models\Invitation::where('email', 'head@egypt.test')->first();
        $this->assertSame($store->id, $inv->company_id);
        $this->assertTrue($inv->as_head);

        // Platform departments are not theirs.
        $platform = \App\Models\Department::whereNull('tenant_id')->where('key', 'sales')->first();
        $this->getJson("/api/departments/{$platform->id}/members")->assertForbidden();
        $this->putJson("/api/departments/{$platform->id}", ['name_en' => 'Hijack'])->assertForbidden();
        $this->assertCount(10, $this->getJson('/api/departments')->json());
    }

    public function test_department_permissions_are_edited_in_the_panel_within_what_the_editor_holds(): void
    {
        $this->seed([IndustrySeeder::class, RolesAndPermissionsSeeder::class]);
        $eg = Tenant::create(['name' => 'ArmorHub Egypt', 'industry_id' => Industry::first()->id, 'market' => 'EG']);
        $store = Company::create(['tenant_id' => $eg->id, 'name' => 'EG Store', 'type' => 'partner', 'status' => 'active']);
        $admin = User::factory()->create(['company_id' => $store->id]);
        $admin->assignRole('company-owner');
        $this->actingAs($admin, 'sanctum');
        $this->postJson('/api/departments/open')->assertOk();
        $sales = \App\Models\Department::where('tenant_id', $eg->id)->where('key', 'sales')->first();

        // The department's area starts from a default and is then changed in the panel; a role is built from it.
        $this->postJson("/api/departments/{$sales->id}/roles", ['name' => 'rep', 'permissions' => ['orders.view', 'orders.create']])->assertCreated();
        $row = $this->putJson("/api/departments/{$sales->id}/scope", ['scope' => ['orders.view', 'products.view', 'products.update']])->assertOk()->json();
        $this->assertTrue($row['can_edit_scope']);
        $this->assertEqualsCanonicalizing(['orders.view', 'products.view', 'products.update'], $sales->fresh()->scope);
        // The role lost what the department no longer covers.
        $role = \Spatie\Permission\Models\Role::where('name', "dept-t{$eg->id}-sales-rep")->first();
        $this->assertSame(['orders.view'], $role->permissions->pluck('name')->all());

        // A market admin cannot hand out platform-level powers, and cannot edit a platform department.
        $this->putJson("/api/departments/{$sales->id}/scope", ['scope' => ['tenants.manage']])->assertUnprocessable();
        $platformDept = \App\Models\Department::whereNull('tenant_id')->where('key', 'sales')->first();
        $this->putJson("/api/departments/{$platformDept->id}/scope", ['scope' => ['orders.view']])->assertForbidden();

        // A role can be removed when nobody holds it.
        $this->deleteJson("/api/departments/{$sales->id}/roles/{$role->id}")->assertNoContent();
    }
}
