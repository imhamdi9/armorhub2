<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Film extends Model
{
    public function priceRules(): HasMany
{
    return $this->hasMany(
        FilmPriceRule::class
    );
}


    protected $fillable = [
        'brand',
        'name',
        'slug',
        'description',
        'base_price',
        'warranty_years',
        'is_active',
    ];


    protected $casts = [
        'base_price'=>'decimal:2',
        'is_active'=>'boolean',
    ];

} 