<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = ['subject_type', 'subject_id', 'plan_key', 'status', 'period_start', 'period_end', 'auto_renew'];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'auto_renew' => 'boolean'];
    }

    public function invoices()
    {
        return $this->hasMany(SubscriptionInvoice::class)->latest('id');
    }
}
