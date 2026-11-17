<?php

namespace App\Services\Ledger;

use App\Models\CentreWalletEntry;
use App\Models\SellerLedgerEntry;

/**
 * Checks the books against each other: the ledger must balance, and what it says each seller and centre is owed must match the seller ledger
 * and the centre wallet the screens show. Anything that does not match is reported, never "fixed" silently.
 */
class LedgerReconciler
{
    public function __construct(private Ledger $ledger)
    {
    }

    /** @return list<string> what is wrong; empty when everything agrees */
    public function problems(): array
    {
        $problems = [];

        foreach ($this->ledger->trialBalance() as $currency => $diff) {
            if (abs($diff) > 0.001) {
                $problems[] = "The ledger does not balance in {$currency}: debits minus credits is {$diff}.";
            }
        }

        $sellers = SellerLedgerEntry::query()->select('company_id', 'currency')->distinct()->get();
        foreach ($sellers as $row) {
            $seen = ['held' => 0.0, 'available' => 0.0, 'in_payout' => 0.0];
            foreach (SellerLedgerEntry::where('company_id', $row->company_id)->where('currency', $row->currency)->get() as $e) {
                $key = match (true) {
                    $e->status === 'held' => 'held',
                    $e->status === 'available' && $e->payout_id !== null => 'in_payout',
                    $e->status === 'available' => 'available',
                    default => null,
                };
                if ($key) {
                    $seen[$key] += (float) $e->amount;
                }
            }
            $c = (int) $row->company_id;
            $problems = [...$problems, ...$this->compare("seller {$c} ({$row->currency})", [
                'held' => [$seen['held'], $this->ledger->balance(Accounts::sellerHeld($c), $row->currency)],
                'available' => [$seen['available'], $this->ledger->balance(Accounts::sellerAvailable($c), $row->currency)],
                'in payout' => [$seen['in_payout'], $this->ledger->balance(Accounts::payoutPending($c), $row->currency)],
            ])];
        }

        $centres = CentreWalletEntry::query()->select('company_id', 'currency')->distinct()->get();
        foreach ($centres as $row) {
            $held = $available = $pending = 0.0;
            foreach (CentreWalletEntry::where('company_id', $row->company_id)->where('currency', $row->currency)->get() as $e) {
                match ($e->status) {
                    'held' => $held += (float) $e->amount,
                    'available' => $available += (float) $e->amount,
                    'in_withdrawal' => [$available += (float) $e->amount, $pending -= (float) $e->amount],
                    'paid' => $available += (float) $e->amount,   // money already sent out of the wallet
                    default => null,
                };
            }
            $c = (int) $row->company_id;
            $problems = [...$problems, ...$this->compare("centre {$c} ({$row->currency})", [
                'held' => [$held, $this->ledger->balance(Accounts::centreHeld($c), $row->currency)],
                'available' => [$available, $this->ledger->balance(Accounts::centreAvailable($c), $row->currency)],
                'withdrawing' => [$pending, $this->ledger->balance(Accounts::withdrawalPending($c), $row->currency)],
            ])];
        }

        return $problems;
    }

    /** @param array<string, array{0: float, 1: float}> $pairs [records, ledger] */
    private function compare(string $who, array $pairs): array
    {
        $out = [];
        foreach ($pairs as $what => [$records, $ledger]) {
            if (abs(round($records, 2) - round($ledger, 2)) > 0.001) {
                $out[] = "{$who}: {$what} is ".round($records, 2).' in the records but '.round($ledger, 2).' in the ledger.';
            }
        }

        return $out;
    }
}
