<?php

namespace Tests\Feature;

use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Services\Ledger\Accounts;
use App\Services\Ledger\Ledger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The money ledger: double entry, no drift, no repeats, no edits. */
class LedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_transaction_must_balance_in_every_currency(): void
    {
        $ledger = app(Ledger::class);

        $ledger->post('t1', 'test', [[Accounts::GATEWAY, 'EGP', 100], [Accounts::REV_COMMISSION, 'EGP', -100]]);
        $this->assertSame(100.0, $ledger->balance(Accounts::GATEWAY, 'EGP'));
        $this->assertSame(100.0, $ledger->balance(Accounts::REV_COMMISSION, 'EGP'));

        // Off by a cent: refused, and nothing is written.
        $this->expectException(\LogicException::class);
        try {
            $ledger->post('t2', 'test', [[Accounts::GATEWAY, 'EGP', 100.01], [Accounts::REV_COMMISSION, 'EGP', -100]]);
        } finally {
            $this->assertSame(1, LedgerTransaction::count());
        }
    }

    public function test_two_currencies_cannot_cover_for_each_other(): void
    {
        $this->expectException(\LogicException::class);
        app(Ledger::class)->post('mix', 'test', [[Accounts::GATEWAY, 'EGP', 100], [Accounts::REV_COMMISSION, 'SAR', -100]]);
    }

    public function test_cents_do_not_drift(): void
    {
        $ledger = app(Ledger::class);
        // 0.1 + 0.2 is not 0.3 in floating point; in cents it is.
        $ledger->post('drift', 'test', [[Accounts::GATEWAY, 'EGP', 0.1], [Accounts::GATEWAY, 'EGP', 0.2], [Accounts::REV_SUBSCRIPTION, 'EGP', -0.3]]);

        $this->assertSame(0.3, $ledger->balance(Accounts::GATEWAY, 'EGP'));
        $this->assertSame(['EGP' => 0.0], $ledger->trialBalance());
    }

    public function test_the_same_event_posts_once(): void
    {
        $ledger = app(Ledger::class);
        $lines = [[Accounts::GATEWAY, 'EGP', 50], [Accounts::REV_SERVICE_FEE, 'EGP', -50]];

        $first = $ledger->post('order:1:paid', 'order_paid', $lines);
        $again = $ledger->post('order:1:paid', 'order_paid', $lines);

        $this->assertSame($first->id, $again->id);
        $this->assertSame(2, LedgerEntry::count());
        $this->assertSame(50.0, $ledger->balance(Accounts::GATEWAY, 'EGP'));
    }

    public function test_nothing_can_be_edited_or_deleted_not_even_around_the_models(): void
    {
        $tx = app(Ledger::class)->post('x', 'test', [[Accounts::GATEWAY, 'EGP', 10], [Accounts::REV_SERVICE_FEE, 'EGP', -10]]);

        foreach ([fn () => $tx->update(['kind' => 'edited']), fn () => LedgerEntry::first()->update(['debit' => 1]), fn () => LedgerEntry::first()->delete()] as $try) {
            try {
                $try();
                $this->fail('The ledger accepted a change.');
            } catch (\LogicException) {
                $this->addToAssertionCount(1);
            }
        }

        // The database itself refuses too.
        foreach (['update ledger_entries set debit = 999', 'delete from ledger_entries', "update ledger_transactions set kind = 'x'", 'delete from ledger_transactions'] as $sql) {
            try {
                \Illuminate\Support\Facades\DB::statement($sql);
                $this->fail("The database accepted: {$sql}");
            } catch (\Illuminate\Database\QueryException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(2, LedgerEntry::count());
    }

    public function test_a_mistake_is_corrected_by_an_opposite_transaction(): void
    {
        $ledger = app(Ledger::class);
        $ledger->post('wrong', 'test', [[Accounts::GATEWAY, 'EGP', 80], [Accounts::REV_COMMISSION, 'EGP', -80]]);
        $ledger->post('wrong:reversal', 'test', [[Accounts::GATEWAY, 'EGP', -80], [Accounts::REV_COMMISSION, 'EGP', 80]]);

        $this->assertSame(0.0, $ledger->balance(Accounts::GATEWAY, 'EGP'));
        $this->assertSame(4, LedgerEntry::count());   // both are still on record
    }

    public function test_the_chart_of_accounts_knows_whose_money_it_is(): void
    {
        $ledger = app(Ledger::class);
        $ledger->post('own', 'test', [[Accounts::GATEWAY, 'EGP', 70], [Accounts::sellerHeld(12), 'EGP', -70]]);

        $account = $ledger->account(Accounts::sellerHeld(12), 'EGP');
        $this->assertSame('liability', $account->type);
        $this->assertSame(['seller', 12], [$account->owner_type, $account->owner_id]);
        $this->assertSame(70.0, $account->balance());   // what is owed to the seller
    }
}
