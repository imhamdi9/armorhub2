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
        $this->app->singleton(\App\Support\Settings\SettingsRegistry::class);
        $this->app->singleton(\App\Support\Settings\Settings::class);
        $this->app->singleton(\App\Support\Markets\Markets::class);

        // Payment providers are drivers; which one is active and its credentials are admin settings.
        $this->app->singleton(\App\Services\Payment\PaymentProviders::class, function ($app) {
            $providers = new \App\Services\Payment\PaymentProviders($app->make(\App\Support\Settings\SettingsRegistry::class), $app->make(\App\Support\Settings\Settings::class));

            foreach ([\App\Services\Payment\Providers\SimulatedProvider::class, \App\Services\Payment\Providers\StripeProvider::class, \App\Services\Payment\Providers\HttpProvider::class] as $driver) {
                $providers->register($app->make($driver));
            }

            return $providers;
        });
        // Shipping providers: the same idea. Staff may pick any configured one per shipment.
        $this->app->singleton(\App\Services\Shipping\ShippingProviders::class, function ($app) {
            $providers = new \App\Services\Shipping\ShippingProviders($app->make(\App\Support\Settings\SettingsRegistry::class), $app->make(\App\Support\Settings\Settings::class));

            foreach ([\App\Services\Shipping\Providers\ManualProvider::class, \App\Services\Shipping\Providers\HttpProvider::class, \App\Services\Shipping\Providers\SimulatedProvider::class] as $driver) {
                $providers->register($app->make($driver));
            }

            return $providers;
        });
        $this->app->bind(PaymentGateway::class, \App\Services\Payment\PaymentManager::class);

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

        // Registers the payment providers' settings so the admin panel and Settings know about them.
        $this->app->make(\App\Services\Payment\PaymentProviders::class);
        $this->app->make(\App\Services\Shipping\ShippingProviders::class);

        // Reset links open the web app, not the API.
        ResetPassword::createUrlUsing(fn ($user, string $token) => rtrim(config('platform.web_url'), '/')
            .'/reset-password?token='.$token.'&email='.urlencode($user->getEmailForPasswordReset()));

        $email = fn (Request $r) => strtolower(trim((string) $r->input('email')));
        $settings = fn () => app(\App\Support\Settings\Settings::class);

        // Password guessing: per person+address, per address across the world (credential stuffing
        // spreads attempts over many IPs), and per IP overall (one machine trying many accounts).
        RateLimiter::for('login', fn (Request $r) => [
            Limit::perMinute(5)->by('login:'.$r->ip().'|'.$email($r)),
            Limit::perHour(40)->by('login-email:'.$email($r)),
            Limit::perMinute(30)->by('login-ip:'.$r->ip()),
        ]);

        // Mass signup (Sybil) is limited by IP, deliberately NOT by email: a bot just rotates emails.
        RateLimiter::for('register', fn (Request $r) => [
            Limit::perHour(max(1, (int) $settings()->get('security.signups_per_ip_per_hour')))->by('register:'.$r->ip()),
            Limit::perDay(30)->by('register-day:'.$r->ip()),
        ]);

        // Reset emails can be abused to spam a victim; cap per target and per source.
        RateLimiter::for('forgot', fn (Request $r) => [
            Limit::perHour(3)->by('forgot:'.$r->ip().'|'.$email($r)),
            Limit::perHour(10)->by('forgot-ip:'.$r->ip()),
        ]);

        RateLimiter::for('resend', fn (Request $r) => Limit::perHour(3)->by('resend:'.($r->user()?->id ?: $r->ip())));
        RateLimiter::for('public', fn (Request $r) => Limit::perMinute(max(1, (int) config('platform.public_rate_limit')))->by('public:'.$r->ip()));
        RateLimiter::for('challenge', fn (Request $r) => Limit::perMinute(20)->by('challenge:'.$r->ip()));
        RateLimiter::for('orders', fn (Request $r) => Limit::perHour(10)->by('orders:'.($r->user()?->id ?: $r->ip())));
        // Orders without an account: per address, because a script would otherwise place orders for anyone.
        RateLimiter::for('guest-orders', fn (Request $r) => [Limit::perMinute(6)->by('guest:'.$r->ip()), Limit::perHour(40)->by('guest-hour:'.$r->ip())]);
        RateLimiter::for('webhook', fn (Request $r) => Limit::perMinute(300)->by('webhook:'.$r->ip()));

        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(120)->by($r->user()?->id ?: $r->ip()));
    }
}
