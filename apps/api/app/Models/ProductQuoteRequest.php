<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductQuoteRequest extends Model
{
    protected $guarded = [];

    protected $casts = ['expires_at' => 'datetime'];

    public function quotes()
    {
        return $this->hasMany(ProductQuote::class, 'request_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
