<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Housekeeping (needs `php artisan schedule:run` every minute, or run these commands from your host's cron).
\Illuminate\Support\Facades\Schedule::command('audit:prune')->daily();
\Illuminate\Support\Facades\Schedule::command('sanctum:prune-expired --hours=24')->daily();
