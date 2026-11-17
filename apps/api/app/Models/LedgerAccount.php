<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LedgerAccount extends Model
{
    protected $fillable = ['code', 'currency', 'type', 'name', 'owner_type', 'owner_id'];

    public function entries()
    {
        return $this->hasMany(LedgerEntry::class, 'account_id');
    }

    /** Debits minus credits. */
    public function debitBalance(): float
    {
        return round((float) $this->entries()->sum('debit') - (float) $this->entries()->sum('credit'), 2);
    }

    /** What the account holds, in its natural direction: assets by debits, everything else by credits. */
    public function balance(): float
    {
        $debit = $this->debitBalance();

        return $this->type === 'asset' || $this->type === 'expense' ? $debit : -$debit;
    }
}
