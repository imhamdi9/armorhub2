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
use App\Services\Payment\PaymentGateway;
use App\Services\Payment\SimulatedGateway;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->app->bind(PaymentGateway::class, fn () => match (config('platform.payment_gateway')) {
            'simulated' => new SimulatedGateway(),
            'stripe' => new \App\Services\Payment\StripeGateway(),
            default => throw new \InvalidArgumentException(
                'Unknown payment gateway ['.config('platform.payment_gateway').'].'
            ),
        });

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

        // Reset links open the web app, not the API.
        ResetPassword::createUrlUsing(fn ($user, string $token) => rtrim(config('platform.web_url'), '/')
            .'/reset-password?token='.$token.'&email='.urlencode($user->getEmailForPasswordReset()));

        // Slow down password guessing and signup abuse.
        RateLimiter::for('auth', fn (Request $r) => Limit::perMinute(10)->by($r->ip().'|'.strtolower((string) $r->input('email'))));

        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(120)->by($r->user()?->id ?: $r->ip()));
    }
}
