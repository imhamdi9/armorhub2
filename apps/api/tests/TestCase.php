<?php

namespace Tests;

use App\Services\Ledger\LedgerReconciler;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    /**
     * Whatever a test did with money, the books must still balance and agree with the seller ledger and the centre wallets.
     * Checked after every test, so a new money flow that forgets the ledger fails the test that exercises it.
     */
    protected function tearDown(): void
    {
        if ($this->app && Schema::hasTable('ledger_entries')) {
            $problems = app(LedgerReconciler::class)->problems();
            $this->assertSame([], $problems, "The ledger disagrees with the records:\n".implode("\n", $problems));
        }

        parent::tearDown();
    }
}
