<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;

use App\Models\Company;
use App\Models\VehicleMake;
use App\Models\InstallationCenter;

use App\Policies\CompanyPolicy;
use App\Policies\VehicleMakePolicy;
use App\Policies\InstallationCenterPolicy;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Company::class, CompanyPolicy::class);

        Gate::policy(VehicleMake::class, VehicleMakePolicy::class);

        Gate::policy(
            InstallationCenter::class,
            InstallationCenterPolicy::class
        );
    }
}