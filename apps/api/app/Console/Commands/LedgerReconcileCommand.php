<?php

namespace App\Console\Commands;

use App\Services\Ledger\LedgerReconciler;
use Illuminate\Console\Command;

/** Checks the books: the ledger must balance and agree with the seller ledger and the centre wallets. */
class LedgerReconcileCommand extends Command
{
    protected $signature = 'ledger:reconcile';

    protected $description = 'Check that the ledger balances and agrees with the seller ledger and the centre wallets';

    public function handle(LedgerReconciler $reconciler): int
    {
        $problems = $reconciler->problems();
        if ($problems === []) {
            $this->info('The ledger balances and agrees with the seller ledger and the centre wallets.');

            return self::SUCCESS;
        }

        foreach ($problems as $p) {
            $this->error($p);
        }

        return self::FAILURE;
    }
}
