<?php

namespace App\Core;

use App\Core\Console\ProcessEvents;
use App\Core\Console\ReplayEvent;
use App\Core\Events\DeliveryProcessor;
use App\Core\Events\EventBus;
use App\Core\Events\EventCatalog;
use App\Core\Events\SubscriberRegistry;
use App\Core\Modules\ModuleRegistry;
use App\Core\Modules\TenantModules;
use Illuminate\Support\ServiceProvider;

/** Wires the platform core. It lists no product: products arrive through config/modules.php. */
class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EventCatalog::class);
        $this->app->singleton(SubscriberRegistry::class);
        $this->app->singleton(TenantModules::class);
        $this->app->singleton(EventBus::class);
        $this->app->singleton(DeliveryProcessor::class);
        $this->app->singleton(ModuleRegistry::class);
    }

    public function boot(): void
    {
        $this->app->make(ModuleRegistry::class)->load((array) config('modules.modules', []));

        if ($this->app->runningInConsole()) {
            $this->commands([ProcessEvents::class, ReplayEvent::class]);
        }
    }
}
