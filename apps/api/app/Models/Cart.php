<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    protected $fillable = ['customer_id', 'promo_code'];

    public function items()
    {
        return $this->hasMany(CartItem::class)->orderBy('id');
    }
}
