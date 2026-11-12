<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Department;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\InvitationSent;
use Database\Seeders\IndustrySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DelegationTest extends TestCase
{
    use RefreshDatabase;

    private Company $hq;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IndustrySeeder::class, RolesAndPermissionsSeeder::class]);
        $tenant = \App\Models\Tenant::create(['name' => 'ArmorHub', 'industry_id' => \App\Models\Industry::first()->id]);
        $this->hq = Company::create(['tenant_id' => $tenant->id, 'name' => 'ArmorHub HQ', 'type' => 'platform', 'status' => 'active']);
        $this->admin = User::factory()->create(['company_id' => $this->hq->id]);
        $this->admin->assignRole('platform-admin');
    }

    /** Sends an invitation and returns the token the e-mail carries. */
    private function invite(User $by, array $body): array
    {
        Notification::fake();
        $res = $this->actingAs($by, 'sanctum')->postJson('/api/invitations', $body);
        $token = null;
        if ($res->status() === 201) {
            Notification::assertSentOnDemand(InvitationSent::class, function ($n, $channels, $notifiable) use (&$token) {
                $token = (new \ReflectionProperty($n, 'token'))->getValue($n);

                return true;
            });
        }

        return [$res, $token];
    }

    private function accept(string $token, string $name = 'New Person'): void
    {
        $this->getJson("/api/public/invitations/{$token}")->assertOk();
        $this->postJson("/api/public/invitations/{$token}/accept", ['name' => $name, 'password' => 'Str0ng-Passw0rd!x'])->assertCreated();
    }

    public function test_the_ten_departments_exist_and_an_admin_names_a_head_who_runs_only_that_department(): void
    {
        $this->assertSame(10, Department::count());
        $cs = Department::where('key', 'customer-service')->first();
        $sales = Department::where('key', 'sales')->first();

        // The admin invites the head of customer service, who accepts.
        [$res, $token] = $this->invite($this->admin, ['email' => 'head@armorhub.test', 'department_id' => $cs->id, 'as_head' => true, 'role' => 'platform-admin']);
        $res->assertStatus(422); // a head needs a role of the department first

        $this->actingAs($this->admin, 'sanctum')->postJson("/api/departments/{$cs->id}/roles", ['name' => 'agent', 'permissions' => ['orders.view', 'orders.update', 'dashboard.view']])->assertCreated();
        // Permissions outside the department's own area are refused.
        $this->postJson("/api/departments/{$cs->id}/roles", ['name' => 'boss', 'permissions' => ['products.update']])->assertStatus(422);
        $this->postJson("/api/departments/{$cs->id}/roles", ['name' => 'lead', 'permissions' => ['orders.view', 'orders.update']])->assertCreated();

        [$res, $token] = $this->invite($this->admin, ['email' => 'head@armorhub.test', 'department_id' => $cs->id, 'as_head' => true, 'role' => 'dept-customer-service-lead']);
        $res->assertCreated();
        $this->accept($token, 'Head CS');
        $head = User::where('email', 'head@armorhub.test')->first();
        $this->assertTrue($head->is_department_head);
        $this->assertSame($cs->id, $head->department_id);
        $this->assertSame($this->hq->id, $head->company_id);
        $this->assertTrue($head->can('orders.update'));
        $this->assertFalse($head->can('products.update'));

        // The head invites an agent into THEIR department with a department role...
        [$res, $t2] = $this->invite($head, ['email' => 'agent@armorhub.test', 'department_id' => $cs->id, 'role' => 'dept-customer-service-agent']);
        $res->assertCreated();
        $this->accept($t2, 'Agent');
        $agent = User::where('email', 'agent@armorhub.test')->first();
        $this->assertSame($cs->id, $agent->department_id);
        $this->assertFalse((bool) $agent->is_department_head);

        // ...but not into another department, not with global roles, and cannot name heads.
        [$r] = $this->invite($head, ['email' => 'x1@armorhub.test', 'department_id' => $sales->id, 'role' => 'dept-customer-service-agent']);
        $r->assertStatus(422);
        [$r] = $this->invite($head, ['email' => 'x2@armorhub.test', 'department_id' => $cs->id, 'role' => 'platform-admin']);
        $r->assertStatus(422)->assertJsonValidationErrors('role');
        [$r] = $this->invite($head, ['email' => 'x3@armorhub.test', 'department_id' => $cs->id, 'role' => 'dept-customer-service-agent', 'as_head' => true]);
        $r->assertStatus(422);

        // The head sees and manages only their own department.
        $this->actingAs($head, 'sanctum')->getJson('/api/departments')->assertOk()->assertJsonCount(1)->assertJsonPath('0.key', 'customer-service');
        $this->getJson("/api/departments/{$cs->id}/members")->assertOk()->assertJsonCount(2);
        $this->getJson("/api/departments/{$sales->id}/members")->assertForbidden();
        $this->postJson("/api/departments/{$sales->id}/roles", ['name' => 'x', 'permissions' => ['orders.view']])->assertForbidden();
        $this->putJson("/api/departments/{$cs->id}", ['name_en' => 'Hijack'])->assertForbidden();
        $this->putJson("/api/departments/{$cs->id}/members/{$agent->id}", ['is_active' => false])->assertOk();
        $this->assertFalse((bool) $agent->fresh()->is_active);
        $this->putJson("/api/departments/{$cs->id}/members/{$head->id}", ['is_active' => false])->assertStatus(422);
        // The agent cannot invite anyone.
        $this->actingAs($agent->fresh(), 'sanctum');
        [$r] = $this->invite($agent, ['email' => 'x4@armorhub.test', 'department_id' => $cs->id, 'role' => 'dept-customer-service-agent']);
        $r->assertStatus(422);
        $this->getJson('/api/departments')->assertForbidden();
    }

    public function test_sellers_market_managers_and_centres_invite_only_their_own_team(): void
    {
        // A seller invites a teammate: placed in the seller's own company, only the seller role.
        $sellerCo = Company::create(['tenant_id' => $this->hq->tenant_id, 'name' => 'Gulf Parts', 'type' => 'supplier', 'status' => 'active']);
        $seller = User::factory()->create(['company_id' => $sellerCo->id]);
        $seller->assignRole('seller');
        [$r, $token] = $this->invite($seller, ['email' => 'mate@gulf.test', 'role' => 'seller', 'company_id' => $this->hq->id]); // company_id is ignored
        $r->assertCreated();
        $this->accept($token);
        $this->assertSame($sellerCo->id, User::where('email', 'mate@gulf.test')->value('company_id'));
        [$r] = $this->invite($seller, ['email' => 'boss@gulf.test', 'role' => 'company-owner']);
        $r->assertStatus(422);
        $this->assertSame(['seller'], $this->actingAs($seller, 'sanctum')->getJson('/api/invitations/options')->json('roles'));

        // A market manager brings in warehouse staff for their market only.
        $mm = User::factory()->create(['company_id' => $this->hq->id]);
        $mm->assignRole('company-owner');
        [$r, $token] = $this->invite($mm, ['email' => 'wh@hq.test', 'role' => 'warehouse-staff']);
        $r->assertCreated();
        $this->accept($token);
        $wh = User::where('email', 'wh@hq.test')->first();
        $this->assertTrue($wh->hasRole('warehouse-staff'));
        $this->assertSame($this->hq->id, $wh->company_id);
        [$r] = $this->invite($mm, ['email' => 'adm@hq.test', 'role' => 'platform-admin']);
        $r->assertStatus(422);

        // Customers and warehouse staff cannot invite at all.
        $customer = User::factory()->create(['company_id' => $this->hq->id]);
        $customer->assignRole('customer');
        foreach ([$customer, $wh] as $who) {
            $this->assertSame([], $this->actingAs($who, 'sanctum')->getJson('/api/invitations/options')->json('roles'));
            [$r] = $this->invite($who, ['email' => 'z'.$who->id.'@x.test', 'role' => 'customer']);
            $r->assertStatus(422);
        }
    }

    public function test_an_invitation_works_once_expires_and_cannot_be_reused(): void
    {
        [$r, $token] = $this->invite($this->admin, ['email' => 'once@x.test', 'role' => 'customer', 'company_id' => $this->hq->id]);
        $r->assertCreated();
        $this->postJson("/api/public/invitations/{$token}/accept", ['name' => 'N', 'password' => 'weak'])->assertStatus(422);
        $this->accept($token);
        $this->getJson("/api/public/invitations/{$token}")->assertNotFound();
        $this->getJson('/api/public/invitations/not-a-token')->assertNotFound();

        // Existing accounts cannot be invited; an expired invitation is gone.
        [$r] = $this->invite($this->admin, ['email' => 'once@x.test', 'role' => 'customer', 'company_id' => $this->hq->id]);
        $r->assertStatus(422)->assertJsonValidationErrors('email');
        [, $t2] = $this->invite($this->admin, ['email' => 'late@x.test', 'role' => 'customer', 'company_id' => $this->hq->id]);
        Invitation::where('email', 'late@x.test')->update(['expires_at' => now()->subMinute()]);
        $this->getJson("/api/public/invitations/{$t2}")->assertNotFound();
    }
}
