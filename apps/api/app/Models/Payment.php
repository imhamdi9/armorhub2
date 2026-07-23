<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompany;

class Payment extends Model
{   
    use BelongsToCompany;

    protected $fillable = [
        'order_id',
        'amount',
        'currency',
        'status',
        'payment_method',
        'transaction_id',
        'company_id',
    ];


    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}