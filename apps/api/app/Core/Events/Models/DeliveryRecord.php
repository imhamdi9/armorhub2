<?php

namespace App\Core\Events\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryRecord extends Model
{
    protected $table = 'event_deliveries';

    protected $guarded = [];

    protected $casts = ['next_attempt_at' => 'datetime', 'delivered_at' => 'datetime'];

    public function event()
    {
        return $this->belongsTo(EventRecord::class, 'event_id');
    }
}
