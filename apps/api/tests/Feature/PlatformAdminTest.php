<?php

namespace Tests\Feature;

use App\Core\Events\Event;
use App\Core\Events\EventBus;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\IndustrySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class PlatformAdminTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
    }

    public function test_only_platform_admins_see_the_platform(): void
    {
        foreach (['/api/admin/platform/modules', '/api/admin/platform/events/catalog', '/api/admin/platform/events'] as $path) {
            foreach ([$this->customer, $this->manager, $this->tech] as $nobody) {
                $this->actingAs($nobody, 'sanctum')->getJson($path)->assertForbidden();
            }
        }
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/admin/platform/modules')->assertUnauthorized();
    }

    public function test_the_admin_sees_modules_the_event_catalog_and_the_event_log(): void
    {
        $this->actingAs($this->admin, 'sanctum');

        $mods = $this->getJson('/api/admin/platform/modules')->assertOk()->json('modules');
        $armor = collect($mods)->firstWhere('id', 'armorhub');
        $this->assertContains('order.paid', $armor['events']);
        $this->assertContains('armorhub.order-notifications', $armor['subscribers']);

        $cat = $this->getJson('/api/admin/platform/events/catalog')->assertOk()->json('events');
        $paid = collect($cat)->firstWhere('name', 'order.paid');
        $this->assertSame('armorhub', $paid['owner']);
        $this->assertArrayHasKey('order_id', $paid['schema']);

        // A real order produces real events, each with its deliveries.
        $this->order();
        $this->actingAs($this->admin, 'sanctum');   // placing the order switched to the customer
        $log = $this->getJson('/api/admin/platform/events?name=order.*')->assertOk();
        $names = collect($log->json('data'))->pluck('name')->all();
        $this->assertContains('order.paid', $names);
        $this->assertContains('order.created', $names);

        $paidEvent = collect($log->json('data'))->firstWhere('name', 'order.paid');
        $this->assertSame('delivered', $paidEvent['deliveries'][0]['status']);
        $one = $this->getJson("/api/admin/platform/events/{$paidEvent['event_id']}")->assertOk();
        $this->assertSame('order', $one->json('aggregate_type'));
        $this->assertSame('armorhub', $one->json('source'));

        $this->getJson('/api/admin/platform/events?status=dead')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_notifications_now_flow_through_the_event_bus(): void
    {
        $id = $this->order();

        // The notification the customer received was produced by a subscriber reacting to order.paid.
        $this->assertTrue($this->customer->notifications()->where('data->kind', 'payment_received')->exists());
        $delivery = \App\Core\Events\Models\DeliveryRecord::whereHas('event', fn ($q) => $q->where('name', 'order.paid')->where('aggregate_id', (string) $id))->first();
        $this->assertSame('armorhub.order-notifications', $delivery->subscriber);
        $this->assertSame('delivered', $delivery->status);
    }

    public function test_a_tenant_can_switch_the_armorhub_module_off_and_notifications_stop(): void
    {
        $tenant = Tenant::first();
        $this->actingAs($this->admin, 'sanctum')->putJson("/api/admin/platform/tenants/{$tenant->id}/modules/armorhub", ['enabled' => false])->assertOk()->assertJsonPath('enabled', false);

        $mods = $this->getJson("/api/admin/platform/modules?tenant_id={$tenant->id}")->json('modules');
        $this->assertFalse(collect($mods)->firstWhere('id', 'armorhub')['enabled']);

        $this->order();
        $this->assertFalse($this->customer->notifications()->where('data->kind', 'payment_received')->exists());   // module off: no reaction
        $this->assertSame('skipped', \App\Core\Events\Models\DeliveryRecord::latest('id')->first()->status);

        // Turned back on, a replay delivers what was skipped.
        $this->actingAs($this->admin, 'sanctum')->putJson("/api/admin/platform/tenants/{$tenant->id}/modules/armorhub", ['enabled' => true])->assertOk();
        $paid = \App\Core\Events\Models\EventRecord::where('name', 'order.paid')->first();
        $this->postJson("/api/admin/platform/events/{$paid->event_id}/replay")->assertOk();
        $this->assertTrue($this->customer->notifications()->where('data->kind', 'payment_received')->exists());

        $this->putJson("/api/admin/platform/tenants/{$tenant->id}/modules/nope", ['enabled' => false])->assertNotFound();
        $this->assertSame(2, \App\Models\AuditLog::where('action', 'platform.module_toggled')->count());
    }
}
