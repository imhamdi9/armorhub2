<?php

namespace App\Console\Commands;

use App\Services\MarketConsolidator;
use Illuminate\Console\Command;

class MergeMarkets extends Command
{
    protected $signature = 'markets:consolidate';

    protected $description = 'Fold older per-industry markets of a country into the country\'s one market (idempotent)';

    public function handle(MarketConsolidator $consolidator): int
    {
        $this->info('Folded '.$consolidator->run().' market(s).');

        return self::SUCCESS;
    }
}
