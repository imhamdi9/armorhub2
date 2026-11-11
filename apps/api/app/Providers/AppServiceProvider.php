<?php

namespace App\Providers;

use App\Models\Company;
use App\Models\Partner;
use App\Modules\Automotive\AutomotiveModule;
use App\Modules\Beauty\BeautyModule;
use App\Modules\Devices\DevicesModule;
use App\Modules\HomeServices\HomeServicesModule;
use App\Modules\IndustryRegistry;
use App\Policies\CompanyPolicy;
use App\Policies\PartnerPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Industry modules enabled on this platform. Add a module here to
     * make a new vertical available.
     *
     * @var list<class-string<\App\Modules\Contracts\IndustryModule>>
     */
    private const MODULES = [
        AutomotiveModule::class,
        HomeServicesModule::class,
        BeautyModule::class,
        DevicesModule::class,
    ];

    public function register(): void
    {
        $this->app->singleton(IndustryRegistry::class, function ($app) {
            $registry = new IndustryRegistry();

            foreach (self::MODULES as $module) {
                $registry->register($app->make($module));
            }

            return $registry;
        });
    }

    public function boot(): void
    {
        Gate::policy(Company::class, CompanyPolicy::class);

        Gate::policy(Partner::class, PartnerPolicy::class);

        $this->app->make(IndustryRegistry::class)->boot();
    }
}
