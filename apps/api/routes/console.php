<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Housekeeping (needs `php artisan schedule:run` every minute, or run these commands from your host's cron).
\Illuminate\Support\Facades\Schedule::command('audit:prune')->daily();
\Illuminate\Support\Facades\Schedule::command('sanctum:prune-expired --hours=24')->daily();
\Illuminate\Support\Facades\Schedule::command('surfaces:publish-due')->everyMinute();

// Installation jobs: release proven jobs after the objection window, close old requests, rebuild centre records. (Also done lazily when people look, for hosts with no scheduler.)
\Illuminate\Support\Facades\Schedule::command('installations:maintain')->hourly();

// Monthly plans: renew paid ones, end the cancelled ones.
\Illuminate\Support\Facades\Schedule::command('subscriptions:renew')->daily();

// Installation requests that get too few answers are widened to the next best centres.
\Illuminate\Support\Facades\Schedule::call(fn () => app(\App\Services\InstallationMatching::class)->widenDue())->everyThirtyMinutes()->name('installations:widen')->withoutOverlapping();

// Customers who left something in their cart get one reminder.
\Illuminate\Support\Facades\Schedule::command('carts:remind')->hourly();

// Paid orders that sellers have not shipped: remind them, then cancel and refund.
\Illuminate\Support\Facades\Schedule::call(fn () => app(\App\Services\SellerEarningsRelease::class)->run())->hourly()->name('earnings:release')->withoutOverlapping();
\Illuminate\Support\Facades\Schedule::call(fn () => app(\App\Services\ShippingDeadlines::class)->run())->hourly()->name('orders:ship-deadlines')->withoutOverlapping();
\Illuminate\Support\Facades\Schedule::command('guests:prune')->daily();

// What the ad accounts spent (read only), so the marketing page can show the cost and return of each campaign.
\Illuminate\Support\Facades\Schedule::command('marketing:sync-spend --days=7')->dailyAt('04:00');

// A sale or lead an ad platform did not accept is sent again (up to 4 tries a day), then the admins are told.
\Illuminate\Support\Facades\Schedule::command('marketing:retry-conversions')->everyFifteenMinutes();
