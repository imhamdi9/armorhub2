<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShipmentEvent extends Model
{
    protected $fillable = ['shipment_id', 'status', 'description', 'location', 'occurred_at', 'dedupe_key'];

    protected $casts = ['occurred_at' => 'datetime'];

    protected $hidden = ['dedupe_key'];
}
