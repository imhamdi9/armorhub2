<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\CommissionDistribution;

class Commission extends Model
{
    protected $fillable = [
        'order_id',
        'amount',
        'percentage',
        'status',
    ];

    public function distributions()
{
    return $this->hasMany(
        CommissionDistribution::class
    );
}


    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}