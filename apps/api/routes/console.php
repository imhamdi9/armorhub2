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
