<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    protected $fillable = ['customer_id', 'promo_code'];

    protected $casts = ['reminded_at' => 'datetime'];

    public function items()
    {
        return $this->hasMany(CartItem::class)->orderBy('id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }
}
