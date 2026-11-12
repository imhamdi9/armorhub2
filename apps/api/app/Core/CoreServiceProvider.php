<?php

namespace App\Core;

use App\Core\Blueprints\BlueprintApplier;
use App\Core\Blueprints\BlueprintRegistry;
use App\Core\Console\ProcessEvents;
use App\Core\Console\PublishDueSurfaces;
use App\Core\Console\ReplayEvent;
use App\Core\Events\DeliveryProcessor;
use App\Core\Events\EventBus;
use App\Core\Events\EventCatalog;
use App\Core\Events\SubscriberRegistry;
use App\Core\Modules\ModuleRegistry;
use App\Core\Modules\TenantModules;
use App\Core\Surfaces\SurfaceRegistry;
use App\Core\Surfaces\SurfaceStore;
use App\Core\Workflow\WorkflowEngine;
use App\Core\Workflow\WorkflowRegistry;
use Illuminate\Support\ServiceProvider;

/** Wires the platform core. It lists no product: products arrive through config/modules.php. */
class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EventCatalog::class);
        $this->app->singleton(SubscriberRegistry::class);
        $this->app->singleton(TenantModules::class);
        $this->app->singleton(SurfaceRegistry::class);
        $this->app->singleton(SurfaceStore::class);
        $this->app->singleton(BlueprintRegistry::class);
        $this->app->singleton(BlueprintApplier::class);
        $this->app->singleton(WorkflowRegistry::class);
        $this->app->singleton(WorkflowEngine::class);
        $this->app->singleton(EventBus::class);
        $this->app->singleton(DeliveryProcessor::class);
        $this->app->singleton(ModuleRegistry::class);
    }

    public function boot(): void
    {
        $this->app->make(ModuleRegistry::class)->load(array_merge([CoreModule::class], (array) config('modules.modules', [])));

        if ($this->app->runningInConsole()) {
            $this->commands([ProcessEvents::class, ReplayEvent::class, PublishDueSurfaces::class]);
        }
    }
}
