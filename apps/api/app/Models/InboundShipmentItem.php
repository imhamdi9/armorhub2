<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InboundShipmentItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['inbound_shipment_id', 'product_id', 'cartons_expected', 'cartons_received'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withoutGlobalScopes();
    }
}
