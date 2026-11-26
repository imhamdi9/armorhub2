<?php

namespace Tests;

use App\Services\Ledger\LedgerReconciler;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Most tests list products in one language; the two-language rule has its own tests, which switch it back on.
        if (Schema::hasTable('settings')) {
            app(\App\Support\Settings\Settings::class)->set('products.require_both_languages', false);
            app(\App\Support\Settings\Settings::class)->set('products.require_approval', false);
        }
    }

    /**
     * Whatever a test did with money, the books must still balance and agree with the seller ledger and the centre wallets.
     * Checked after every test, so a new money flow that forgets the ledger fails the test that exercises it.
     */
    protected function tearDown(): void
    {
        \App\Models\Product::$rawNames = false;        // a controller may have set it for its own request
        if ($this->app && Schema::hasTable('ledger_entries')) {
            $problems = app(LedgerReconciler::class)->problems();
            $this->assertSame([], $problems, "The ledger disagrees with the records:\n".implode("\n", $problems));
        }

        parent::tearDown();
    }
}
