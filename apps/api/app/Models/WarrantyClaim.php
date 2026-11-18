<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A customer's claim under a warranty: what went wrong and what the seller decided. */
class WarrantyClaim extends Model
{
    public const OPEN = 'open';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';
    public const RESOLVED = 'resolved';

    protected $guarded = [];

    protected $casts = ['decided_at' => 'datetime', 'resolved_at' => 'datetime'];

    public function warranty(): BelongsTo
    {
        return $this->belongsTo(Warranty::class)->withoutGlobalScopes();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withoutGlobalScopes();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }
}
