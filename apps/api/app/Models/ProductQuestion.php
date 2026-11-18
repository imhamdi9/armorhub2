<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A shopper's question about a product and the seller's answer. */
class ProductQuestion extends Model
{
    protected $fillable = ['product_id', 'user_id', 'question', 'answer', 'answered_by', 'answered_at', 'status'];

    protected $casts = ['answered_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withoutGlobalScopes();
    }

    /** What every visitor may read: answered and not hidden. */
    public function scopePublic($query)
    {
        return $query->where('status', 'published')->whereNotNull('answer');
    }
}
