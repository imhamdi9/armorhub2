<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** The business numbers: only administrators see them, and every figure comes from records. */
class KpiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->artisan('platform:demo-egypt')->assertSuccessful();
    }

    public function test_only_platform_administrators_see_the_numbers(): void
    {
        $this->getJson('/api/admin/kpis')->assertUnauthorized();
        $this->actingAs(User::where('email', 'nour.fawzy@demo.eg')->firstOrFail(), 'sanctum')->getJson('/api/admin/kpis')->assertForbidden();
    }

    public function test_the_report_has_every_figure_and_the_pilot_targets(): void
    {
        $this->actingAs(User::role('platform-admin')->firstOrFail(), 'sanctum');
        $r = $this->getJson('/api/admin/kpis?market=EG')->assertOk()->json();

        foreach (['period', 'gmv', 'net_revenue', 'take_rate', 'average_order_value', 'active', 'rfq', 'orders', 'repeat_purchase_rate', 'pilot'] as $key) {
            $this->assertArrayHasKey($key, $r);
        }
        $this->assertSame(15, $r['pilot']['centres']['target']);
        $this->assertSame(20, $r['pilot']['sellers']['target']);
        $this->assertSame(100, $r['pilot']['requests_per_month']['target']);
        $this->getJson('/api/admin/kpis?from=2026-12-10&to=2026-12-01')->assertUnprocessable();
    }
}
