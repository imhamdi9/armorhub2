<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use \App\Models\Concerns\Auditable;
    public function priceRules(): HasMany
{
    return $this->hasMany(
        ProductPriceRule::class
    );
}


    protected $fillable = [
        'industry_id',
        'market',
        'category',
        'brand',
        'name',
        'slug',
        'description',
        'base_price',
        'warranty_years',
        'attributes',
        'is_active',
    ];


    protected $casts = [
        'base_price'=>'decimal:2',
        'is_active'=>'boolean',
        'attributes'=>'array',
    ];

    public function industry(): BelongsTo
    {
        return $this->belongsTo(Industry::class);
    }
}
