<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InboundShipment extends Model
{
    protected $fillable = ['tenant_id', 'warehouse_id', 'seller_id', 'status', 'carrier', 'tracking_number', 'note', 'received_by', 'received_at'];

    protected $casts = ['received_at' => 'datetime'];

    public function items(): HasMany
    {
        return $this->hasMany(InboundShipmentItem::class);
    }
}
