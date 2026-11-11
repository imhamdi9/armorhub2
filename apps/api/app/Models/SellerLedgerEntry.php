<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerLedgerEntry extends Model
{
    protected $guarded = [];

    protected $casts = ['amount' => 'decimal:2', 'available_at' => 'datetime'];

    public function order()
    {
        return $this->belongsTo(Order::class)->withoutGlobalScopes();
    }
}
