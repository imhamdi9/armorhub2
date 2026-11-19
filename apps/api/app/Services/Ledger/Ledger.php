<?php

namespace App\Services\Ledger;

use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * The money ledger. A transaction is a set of lines that add up to zero (debits positive, credits negative), so money is never created or lost.
 * Posting the same event twice is harmless (the idempotency key), and nothing is ever edited: a correction is a new, opposite transaction.
 */
class Ledger
{
    /**
     * @param  array<int, array{0: string, 1: string, 2: float|int|string}>  $lines  [account code, currency, signed amount: + debit, - credit]
     * @param  array<string, array{type: string, name: string, owner?: array{0: string, 1: int}}>  $accounts  how to create an account that does not exist yet, by code
     */
    public function post(string $key, string $kind, array $lines, ?Model $reference = null, string $description = '', array $meta = [], ?int $by = null, array $accounts = []): ?LedgerTransaction
    {
        if ($existing = LedgerTransaction::where('idempotency_key', $key)->first()) {
            return $existing;
        }

        // Work in whole minor units so there is no floating point drift: every currency must balance on its own.
        $cents = [];
        $byCurrency = [];
        foreach ($lines as [$code, $currency, $amount]) {
            $minor = (int) round(((float) $amount) * 100);
            if ($minor === 0) {
                continue;
            }
            $cents[] = [$code, strtoupper($currency), $minor];
            $byCurrency[strtoupper($currency)] = ($byCurrency[strtoupper($currency)] ?? 0) + $minor;
        }

        if (count($cents) < 2) {
            return null;    // nothing moved
        }
        foreach ($byCurrency as $currency => $sum) {
            if ($sum !== 0) {
                throw new \LogicException("Ledger transaction {$key} does not balance in {$currency} (off by ".($sum / 100).').');
            }
        }

        try {
            return DB::transaction(function () use ($key, $kind, $cents, $reference, $description, $meta, $by, $accounts) {
                $tx = LedgerTransaction::create([
                    'idempotency_key' => $key, 'kind' => $kind, 'description' => $description ?: null, 'meta' => $meta ?: null, 'created_by' => $by,
                    'reference_type' => $reference ? class_basename($reference) : null, 'reference_id' => $reference?->getKey(), 'posted_at' => now(),
                ]);

                foreach ($cents as [$code, $currency, $minor]) {
                    $account = $this->account($code, $currency, $accounts[$code] ?? null);
                    LedgerEntry::create([
                        'transaction_id' => $tx->id, 'account_id' => $account->id, 'currency' => $currency, 'posted_at' => $tx->posted_at,
                        'debit' => $minor > 0 ? $minor / 100 : 0, 'credit' => $minor < 0 ? -$minor / 100 : 0,
                    ]);
                }

                return $tx;
            });
        } catch (UniqueConstraintViolationException) {
            // Another request posted the same event a moment ago.
            return LedgerTransaction::where('idempotency_key', $key)->first();
        }
    }

    /** @param array{type: string, name: string, owner?: array{0: string, 1: int}}|null $spec */
    public function account(string $code, string $currency, ?array $spec = null): LedgerAccount
    {
        $spec ??= Accounts::describe($code);

        return LedgerAccount::firstOrCreate(['code' => $code, 'currency' => $currency], [
            'type' => $spec['type'], 'name' => $spec['name'], 'owner_type' => $spec['owner'][0] ?? null, 'owner_id' => $spec['owner'][1] ?? null,
        ]);
    }

    /** What an account holds now, in its natural direction (a liability's balance is what is owed). */
    public function balance(string $code, string $currency): float
    {
        $account = LedgerAccount::where('code', $code)->where('currency', strtoupper($currency))->first();

        return $account ? $account->balance() : 0.0;
    }

    /** Sum of every debit minus every credit, per currency: zero when the books are sound. @return array<string, float> */
    public function trialBalance(): array
    {
        return LedgerEntry::query()->selectRaw('currency, round(sum(debit) - sum(credit), 2) as diff')->groupBy('currency')->pluck('diff', 'currency')
            ->map(fn ($v) => round((float) $v, 2))->all();
    }
}
