<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A customer's rating of a product they bought and received. */
class ProductReview extends Model
{
    protected $fillable = ['product_id', 'user_id', 'rating', 'comment', 'status', 'photos'];

    protected $casts = ['photos' => 'array'];

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class)->withoutGlobalScopes();
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }
}
