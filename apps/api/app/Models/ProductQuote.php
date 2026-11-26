<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductQuote extends Model
{
    protected $guarded = [];

    protected $casts = ['valid_until' => 'datetime', 'unit_price' => 'decimal:2'];

    public function request()
    {
        return $this->belongsTo(ProductQuoteRequest::class, 'request_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
