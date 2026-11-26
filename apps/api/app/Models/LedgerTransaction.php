<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One movement of money. Append only: it is never updated or deleted (the database refuses it too). */
class LedgerTransaction extends Model
{
    public $timestamps = false;

    protected $fillable = ['idempotency_key', 'kind', 'description', 'reference_type', 'reference_id', 'meta', 'created_by', 'posted_at'];

    protected $casts = ['meta' => 'array', 'posted_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('The ledger is append-only.'));
        static::deleting(fn () => throw new \LogicException('The ledger is append-only.'));
    }

    public function entries()
    {
        return $this->hasMany(LedgerEntry::class, 'transaction_id');
    }
}
