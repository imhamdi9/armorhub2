<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderRefundRequest extends Model
{
    protected $guarded = [];

    protected $casts = ['decided_at' => 'datetime'];

    public function order()
    {
        return $this->belongsTo(Order::class)->withoutGlobalScopes();
    }
}
