<?php

namespace App\Core\Console;

use App\Core\Events\DeliveryProcessor;
use Illuminate\Console\Command;

/** Delivers events that are waiting or due for a retry. Schedule it every minute on hosts without a queue worker. */
class ProcessEvents extends Command
{
    protected $signature = 'events:process {--limit=200}';

    protected $description = 'Deliver pending and retryable events to their subscribers';

    public function handle(DeliveryProcessor $processor): int
    {
        $this->info('Attempted '.$processor->process(null, (int) $this->option('limit')).' delivery(ies).');

        return self::SUCCESS;
    }
}
