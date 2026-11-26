<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One line of a transaction: a debit or a credit on one account. Append only. */
class LedgerEntry extends Model
{
    public $timestamps = false;

    protected $fillable = ['transaction_id', 'account_id', 'currency', 'debit', 'credit', 'posted_at'];

    protected $casts = ['debit' => 'decimal:2', 'credit' => 'decimal:2', 'posted_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('The ledger is append-only.'));
        static::deleting(fn () => throw new \LogicException('The ledger is append-only.'));
    }

    public function account()
    {
        return $this->belongsTo(LedgerAccount::class, 'account_id');
    }

    public function transaction()
    {
        return $this->belongsTo(LedgerTransaction::class, 'transaction_id');
    }
}
