<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromoCode extends Model
{
    protected $guarded = [];

    protected $casts = ['product_ids' => 'array', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_active' => 'boolean', 'value' => 'decimal:2', 'max_discount' => 'decimal:2', 'min_order' => 'decimal:2'];
}
