<?php

namespace App\Console\Commands;

use App\Services\Ledger\LedgerBackfill;
use Illuminate\Console\Command;

/** Adds money that moved before the ledger existed. Safe to run again. */
class LedgerBackfillCommand extends Command
{
    protected $signature = 'ledger:backfill';

    protected $description = 'Post money that moved before the ledger existed (safe to run again)';

    public function handle(LedgerBackfill $backfill): int
    {
        $result = $backfill->run();
        $this->info("Looked at {$result['posted']} records.");
        foreach ($result['skipped'] as $line) {
            $this->warn($line);
        }

        return self::SUCCESS;
    }
}
