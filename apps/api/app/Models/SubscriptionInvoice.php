<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionInvoice extends Model
{
    protected $fillable = ['subscription_id', 'plan_key', 'amount', 'currency', 'status', 'period_start', 'period_end', 'reference', 'paid_at'];

    protected function casts(): array
    {
        return ['amount' => 'float', 'period_start' => 'date', 'period_end' => 'date', 'paid_at' => 'datetime'];
    }
}
