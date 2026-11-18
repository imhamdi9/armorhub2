<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderRefundRequest extends Model
{
    protected $guarded = [];

    protected $casts = ['photos' => 'array', 'decided_at' => 'datetime', 'collected_at' => 'datetime', 'inspected_at' => 'datetime', 'pickup_day' => 'date'];

    protected $hidden = ['return_code'];

    public function order()
    {
        return $this->belongsTo(Order::class)->withoutGlobalScopes();
    }
}
