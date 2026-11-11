<?php

namespace App\Core\Console;

use App\Core\Events\EventBus;
use Illuminate\Console\Command;

class ReplayEvent extends Command
{
    protected $signature = 'events:replay {event : the event id (uuid)} {--subscriber= : only this subscriber}';

    protected $description = 'Send an event again to its subscribers';

    public function handle(EventBus $bus): int
    {
        $this->info('Reset '.$bus->replay((string) $this->argument('event'), $this->option('subscriber') ?: null).' delivery(ies).');

        return self::SUCCESS;
    }
}
