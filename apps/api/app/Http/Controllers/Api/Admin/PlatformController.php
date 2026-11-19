<?php

namespace App\Http\Controllers\Api\Admin;

use App\Core\Events\EventBus;
use App\Core\Events\EventCatalog;
use App\Core\Events\Models\DeliveryRecord;
use App\Core\Events\Models\EventRecord;
use App\Core\Events\SubscriberRegistry;
use App\Core\Modules\ModuleRegistry;
use App\Core\Modules\TenantModules;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Support\Audit;
use Illuminate\Http\Request;

/** The operator's view of the platform core: which modules are installed, what events exist, what happened to them. */
class PlatformController extends Controller
{
    /** Installed modules and what each contributes; with `tenant_id`, whether each is on for that tenant. */
    public function modules(Request $request, ModuleRegistry $modules, EventCatalog $catalog, SubscriberRegistry $subscribers, TenantModules $tenantModules)
    {
        $request->validate(['tenant_id' => 'nullable|integer|exists:tenants,id']);
        $tenantId = $request->filled('tenant_id') ? $request->integer('tenant_id') : null;

        return ['modules' => collect($modules->all())->map(function ($module, $id) use ($catalog, $subscribers, $tenantModules, $tenantId) {
            $m = $module->manifest();

            return [
                'id' => $id, 'name' => $m->name, 'version' => $m->version, 'description' => $m->description, 'depends_on' => $m->dependsOn,
                'events' => collect($catalog->all())->filter(fn ($d) => $d->owner === $id)->keys()->values(),
                'subscribers' => collect($subscribers->all())->filter(fn ($s) => $s['module'] === $id)->keys()->values(),
                'enabled' => $tenantId ? $tenantModules->enabled($tenantId, $id) : null,
            ];
        })->values()];
    }

    public function setModule(Request $request, Tenant $tenant, string $module, ModuleRegistry $modules, TenantModules $tenantModules)
    {
        abort_unless($modules->get($module), 404);
        $enabled = $request->validate(['enabled' => 'required|boolean'])['enabled'];

        $tenantModules->set($tenant->id, $module, $enabled);
        Audit::record('platform.module_toggled', $tenant, ['module' => $module, 'enabled' => $enabled]);

        return ['module' => $module, 'tenant_id' => $tenant->id, 'enabled' => $enabled];
    }

    public function catalog(EventCatalog $catalog)
    {
        return ['events' => collect($catalog->all())->map(fn ($d) => [
            'name' => $d->name, 'owner' => $d->owner, 'version' => $d->version, 'description' => $d->description, 'schema' => $d->schema,
        ])->values()];
    }

    public function events(Request $request)
    {
        $request->validate(['name' => 'nullable|string|max:120', 'status' => 'nullable|in:pending,processing,delivered,failed,dead,skipped', 'tenant_id' => 'nullable|integer', 'per_page' => 'nullable|integer|min:1|max:100']);

        return EventRecord::query()
            ->when($request->filled('name'), fn ($q) => $q->where('name', 'like', str_replace('*', '%', $request->query('name'))))
            ->when($request->filled('tenant_id'), fn ($q) => $q->where('tenant_id', $request->integer('tenant_id')))
            ->when($request->filled('status'), fn ($q) => $q->whereHas('deliveries', fn ($d) => $d->where('status', $request->query('status'))))
            ->with('deliveries:id,event_id,subscriber,status,attempts')
            ->latest('id')->paginate($request->integer('per_page', 25));
    }

    public function event(string $eventId)
    {
        abort_unless(\Illuminate\Support\Str::isUuid($eventId), 404);

        return EventRecord::with('deliveries')->where('event_id', $eventId)->firstOrFail();
    }

    public function replay(Request $request, string $eventId, EventBus $bus)
    {
        abort_unless(\Illuminate\Support\Str::isUuid($eventId), 404);
        $data = $request->validate(['subscriber' => 'nullable|string|max:150']);
        $reset = $bus->replay($eventId, $data['subscriber'] ?? null);
        Audit::record('platform.event_replayed', null, ['event' => $eventId, 'subscriber' => $data['subscriber'] ?? null]);

        return ['reset' => $reset, 'deliveries' => DeliveryRecord::whereHas('event', fn ($q) => $q->where('event_id', $eventId))->get(['subscriber', 'status', 'attempts', 'last_error'])];
    }
}
