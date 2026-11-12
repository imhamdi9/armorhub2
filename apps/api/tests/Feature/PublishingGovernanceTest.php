<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class PublishingGovernanceTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private User $second;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
        $this->second = User::factory()->create(['email_verified_at' => now()]);
        $this->second->assignRole('platform-admin');
    }

    private function draft(string $title = 'Hello'): void
    {
        $this->actingAs($this->admin, 'sanctum')->putJson('/api/admin/surfaces/site.home', ['locale' => 'en', 'values' => ['hero_title' => $title]])->assertOk();
    }

    private function live(): string
    {
        $this->app['auth']->forgetGuards();

        return (string) $this->getJson('/api/public/surfaces/site.home')->assertOk()->json('values.en.hero_title');
    }

    private function requireApproval(): void
    {
        app(Settings::class)->set('content.require_approval', true, $this->admin);
    }

    public function test_with_approval_on_a_second_admin_must_approve_before_it_goes_live(): void
    {
        $this->requireApproval();
        $this->draft('Needs approval');

        $id = $this->actingAs($this->admin, 'sanctum')->postJson('/api/admin/surfaces/site.home/publish', ['locale' => 'en'])->assertStatus(202)->assertJsonPath('status', 'pending')->json('id');
        $this->assertSame('', $this->live());                                              // nothing changed for visitors

        $this->actingAs($this->admin, 'sanctum')->postJson("/api/admin/surface-publications/{$id}/approve")->assertStatus(422);   // not your own
        $this->assertSame('', $this->live());

        $this->actingAs($this->second, 'sanctum')->postJson("/api/admin/surface-publications/{$id}/approve")->assertOk()->assertJsonPath('status', 'published');
        $this->assertSame('Needs approval', $this->live());
        $this->actingAs($this->second, 'sanctum')->postJson("/api/admin/surface-publications/{$id}/approve")->assertStatus(422);  // already decided
    }

    public function test_only_platform_admins_see_or_decide(): void
    {
        foreach ([$this->customer, $this->manager] as $nobody) {
            $this->actingAs($nobody, 'sanctum')->getJson('/api/admin/surface-publications')->assertForbidden();
            $this->postJson('/api/admin/surface-publications/1/approve')->assertForbidden();
        }
    }

    public function test_editing_after_the_request_makes_it_stale_instead_of_publishing_unreviewed_content(): void
    {
        $this->requireApproval();
        $this->draft('Reviewed');
        $id = $this->postJson('/api/admin/surfaces/site.home/publish', ['locale' => 'en'])->assertStatus(202)->json('id');
        $this->draft('Sneaked in after review');

        $this->actingAs($this->second, 'sanctum')->postJson("/api/admin/surface-publications/{$id}/approve")->assertOk()->assertJsonPath('status', 'stale');
        $this->assertSame('', $this->live());
    }

    public function test_reject_and_cancel(): void
    {
        $this->requireApproval();
        $this->draft('A');
        $a = $this->postJson('/api/admin/surfaces/site.home/publish', ['locale' => 'en'])->json('id');
        $this->actingAs($this->second, 'sanctum')->postJson("/api/admin/surface-publications/{$a}/reject")->assertOk()->assertJsonPath('status', 'rejected');

        $this->draft('B');
        $b = $this->postJson('/api/admin/surfaces/site.home/publish', ['locale' => 'en'])->json('id');
        $this->postJson("/api/admin/surface-publications/{$b}/cancel")->assertOk()->assertJsonPath('status', 'cancelled');
        $this->assertSame('', $this->live());

        // A newer request replaces an older one that is still waiting.
        $this->draft('C');
        $c1 = $this->postJson('/api/admin/surfaces/site.home/publish', ['locale' => 'en'])->json('id');
        $this->postJson('/api/admin/surfaces/site.home/publish', ['locale' => 'en'])->assertStatus(202);
        $this->postJson("/api/admin/surface-publications/{$c1}/approve")->assertStatus(422);
    }

    public function test_scheduled_content_goes_live_when_its_time_comes_without_a_scheduler(): void
    {
        $this->draft('Scheduled headline');
        $at = now()->addHours(2);

        $this->actingAs($this->admin, 'sanctum')->postJson('/api/admin/surfaces/site.home/publish', ['locale' => 'en', 'publish_at' => $at->toIso8601String()])
            ->assertStatus(202)->assertJsonPath('status', 'scheduled');
        $this->assertSame('', $this->live());

        $this->travelTo(now()->addHours(3));
        \Illuminate\Support\Facades\Cache::flush();                                        // the 15-second guard
        $this->assertSame('Scheduled headline', $this->live());

        $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/surface-publications')->assertOk()
            ->assertJsonPath('open', [])->assertJsonPath('recent.0.status', 'published');
    }

    public function test_a_past_time_is_refused_and_a_scheduled_change_can_be_cancelled(): void
    {
        $this->draft('X');
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/admin/surfaces/site.home/publish', ['locale' => 'en', 'publish_at' => now()->subHour()->toIso8601String()])->assertStatus(422);

        $id = $this->postJson('/api/admin/surfaces/site.home/publish', ['locale' => 'en', 'publish_at' => now()->addDay()->toIso8601String()])->json('id');
        $this->postJson("/api/admin/surface-publications/{$id}/cancel")->assertOk();
        $this->travelTo(now()->addDays(2));
        \Illuminate\Support\Facades\Cache::flush();
        $this->assertSame('', $this->live());
    }

    public function test_approval_with_a_time_waits_for_the_time_after_approval(): void
    {
        $this->requireApproval();
        $this->draft('Both');
        $id = $this->actingAs($this->admin, 'sanctum')->postJson('/api/admin/surfaces/site.home/publish', ['locale' => 'en', 'publish_at' => now()->addDay()->toIso8601String()])->json('id');

        $this->actingAs($this->second, 'sanctum')->postJson("/api/admin/surface-publications/{$id}/approve")->assertOk()->assertJsonPath('status', 'scheduled');
        $this->assertSame('', $this->live());
        $this->travelTo(now()->addDays(2));
        \Illuminate\Support\Facades\Cache::flush();
        $this->assertSame('Both', $this->live());
    }

    public function test_the_command_publishes_due_changes_too(): void
    {
        $this->draft('Via command');
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/admin/surfaces/site.home/publish', ['locale' => 'en', 'publish_at' => now()->addHour()->toIso8601String()])->assertStatus(202);
        $this->travelTo(now()->addHours(2));
        $this->artisan('surfaces:publish-due')->expectsOutputToContain('Published 1')->assertSuccessful();
        $this->assertSame('Via command', $this->live());
    }
}
