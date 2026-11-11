<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;


class ProductPriceRule extends Model
{

    protected $fillable = [
        'product_id',
        'target_type',
        'target_id',
        'price',
        'duration_minutes',
        'is_active',
    ];


    protected $casts = [
        'price'=>'decimal:2',
        'is_active'=>'boolean',
    ];


    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }


    /** Industry-specific target (vehicle part, room, ...). */
    public function target(): MorphTo
    {
        return $this->morphTo();
    }

}