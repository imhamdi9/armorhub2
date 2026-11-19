<?php

namespace Tests\Feature;

use App\Models\Commission;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** Monthly plans: a customer limit, a commission by plan, and an administrator who sets them. */
class SubscriptionTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
    }

    public function test_a_partner_sees_the_free_plan_first_and_the_paid_plans_to_choose_from(): void
    {
        $res = $this->actingAs($this->manager, 'sanctum')->getJson('/api/subscription')->assertOk();
        $res->assertJsonPath('plan.key', 'free')->assertJsonPath('plan.customer_limit', 15)->assertJsonPath('usage.customers', 0);
        $this->assertSame(['free', 'growth', 'pro'], array_column($res->json('plans'), 'key'));

        // A customer has no plan to manage.
        $this->actingAs($this->customer, 'sanctum')->getJson('/api/subscription')->assertForbidden();
    }

    public function test_a_paid_plan_is_charged_and_lowers_the_commission(): void
    {
        $this->actingAs($this->manager, 'sanctum')->postJson('/api/subscription', ['plan' => 'growth'])->assertOk()->assertJsonPath('plan.key', 'growth');
        $sub = Subscription::first();
        $this->assertSame('growth', $sub->plan_key);
        $this->assertSame(299.0, (float) $sub->invoices()->first()->amount);

        $id = $this->order(true);
        $commission = Commission::where('order_id', $id)->first();
        $this->assertEquals(8.0, (float) $commission->percentage);
    }

    public function test_free_plan_commission_is_the_platform_default(): void
    {
        $id = $this->order(true);
        $this->assertEquals(10.0, (float) Commission::where('order_id', $id)->first()->percentage);
    }

    public function test_a_partner_at_its_customer_limit_cannot_send_more_quotes_until_it_upgrades(): void
    {
        SubscriptionPlan::where('key', 'free')->update(['customer_limit' => 1]);
        $this->order(true); // one customer served this month

        $second = $this->actingAs($this->customer, 'sanctum')->postJson('/api/orders', [
            'product_id' => $this->product->id, 'target_type' => 'vehicle_part', 'targets' => [$this->hood->id],
        ])->assertCreated()->json('order.id');
        $quote = \App\Models\PartnerQuote::withoutGlobalScopes()->where('order_id', $second)->where('partner_id', $this->shop->id)->first();

        $this->actingAs($this->manager, 'sanctum')->patchJson("/api/partner-quotes/{$quote->id}", ['price' => 200])->assertUnprocessable()->assertJsonValidationErrors('plan');

        $this->postJson('/api/subscription', ['plan' => 'growth'])->assertOk();
        $this->patchJson("/api/partner-quotes/{$quote->id}", ['price' => 200])->assertOk();
    }

    public function test_only_a_platform_admin_edits_plans_and_the_free_plan_stays_on(): void
    {
        $plan = SubscriptionPlan::where('key', 'growth')->first();

        $this->actingAs($this->manager, 'sanctum')->putJson("/api/admin/subscription-plans/{$plan->id}", ['price_monthly' => 1])->assertForbidden();

        $this->actingAs($this->admin, 'sanctum')->putJson("/api/admin/subscription-plans/{$plan->id}", ['price_monthly' => 349, 'customer_limit' => 80, 'commission_percentage' => 7.5])->assertOk();
        $this->assertSame(349.0, (float) $plan->fresh()->price_monthly);
        $this->assertSame(80, $plan->fresh()->customer_limit);

        $free = SubscriptionPlan::where('key', 'free')->first();
        $this->putJson("/api/admin/subscription-plans/{$free->id}", ['is_active' => false])->assertUnprocessable();
        $this->putJson("/api/admin/subscription-plans/{$plan->id}", ['commission_percentage' => 120])->assertUnprocessable();
    }

    public function test_renewal_charges_again_and_a_cancelled_plan_falls_back_to_free(): void
    {
        $subs = app(SubscriptionService::class);
        $subs->choose('partner', $this->shop->id, 'pro');
        $sub = Subscription::first();
        $sub->update(['period_end' => now()->subDay()->toDateString()]);

        $this->assertSame([1, 0], $subs->renewDue());
        $this->assertSame(2, $sub->invoices()->count());

        $subs->cancel('partner', $this->shop->id);
        $sub->fresh()->update(['period_end' => now()->subDay()->toDateString()]);
        $this->assertSame([0, 1], $subs->renewDue());
        $this->assertSame('free', $sub->fresh()->plan_key);
    }

    public function test_the_plan_picked_at_signup_starts_when_the_application_is_approved(): void
    {
        $app = \App\Models\PartnerApplication::create([
            'type' => 'seller', 'tenant_id' => $this->manager->company->tenant_id, 'business_name' => 'Plan Seller', 'contact_name' => 'Sam', 'email' => 'sam@planseller.test',
            'email_canonical' => 'sam@planseller.test', 'phone' => '+966500000001', 'city' => 'Riyadh', 'country' => 'SA', 'registration_number' => 'CR-1', 'tax_number' => '300000000000003',
            'password' => bcrypt('x'), 'status' => 'pending', 'plan_key' => 'growth', 'plan_pay_token' => 'tok123', 'plan_paid_at' => now(), 'plan_payment_reference' => 'simulated-abc',
        ]);

        $approved = app(\App\Services\PartnerApplicationService::class)->approve($app, $this->manager);

        $company = \App\Models\User::find($approved->user_id)->company_id;
        $this->assertSame('growth', Subscription::where('subject_type', 'seller')->where('subject_id', $company)->value('plan_key'));
    }

    public function test_a_paid_plan_is_paid_after_applying_and_an_unpaid_one_starts_free(): void
    {
        $make = fn (string $email) => \App\Models\PartnerApplication::create([
            'type' => 'seller', 'tenant_id' => $this->manager->company->tenant_id, 'business_name' => $email, 'contact_name' => 'Sam', 'email' => $email,
            'email_canonical' => $email, 'phone' => '+966500000002', 'city' => 'Riyadh', 'country' => 'SA', 'registration_number' => $email, 'tax_number' => '300000000000003',
            'password' => bcrypt('x'), 'status' => 'pending', 'plan_key' => 'pro', 'plan_pay_token' => 'secret-token',
        ]);
        $paying = $make('a@planseller.test');
        $unpaid = $make('b@planseller.test');

        $this->postJson("/api/partner-applications/{$paying->id}/pay-plan", ['token' => 'wrong'])->assertNotFound();
        $this->postJson("/api/partner-applications/{$paying->id}/pay-plan", ['token' => 'secret-token'])->assertOk()->assertJsonPath('paid', true);

        $service = app(\App\Services\PartnerApplicationService::class);
        $a = $service->approve($paying->fresh(), $this->manager);
        $b = $service->approve($unpaid->fresh(), $this->manager);

        $sub = fn ($app) => app(\App\Services\SubscriptionService::class)->subscriptionFor('seller', \App\Models\User::find($app->user_id)->company_id);
        $this->assertSame('pro', $sub($a)->plan_key);
        $this->assertStringStartsWith('simulated-', $sub($a)->invoices()->first()->reference);
        $this->assertSame('free', $sub($b)->plan_key);
    }
}
