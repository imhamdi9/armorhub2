<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    use Concerns\Auditable;

    public const CREATED = 'created';
    public const IN_TRANSIT = 'in_transit';
    public const OUT_FOR_DELIVERY = 'out_for_delivery';
    public const DELIVERED = 'delivered';
    public const FAILED = 'failed';
    public const RETURNED = 'returned';
    public const CANCELLED = 'cancelled';

    public const STATUSES = [self::CREATED, self::IN_TRANSIT, self::OUT_FOR_DELIVERY, self::DELIVERED, self::FAILED, self::RETURNED, self::CANCELLED];

    /** Once a shipment is in one of these, late or replayed carrier events cannot change it. */
    public const FINAL = [self::DELIVERED, self::RETURNED, self::CANCELLED];

    protected $fillable = ['order_id', 'provider', 'carrier', 'service', 'reference', 'tracking_number', 'tracking_url', 'label_url',
        'status', 'cost', 'currency', 'created_by', 'delivered_at', 'delivery_code', 'attempts', 'failed_reason', 'reschedule_for', 'reschedule_note'];

    /** A parcel gets this many failed attempts before the customer must contact support. */
    public const MAX_ATTEMPTS = 3;

    /** The code is the customer's: staff and tracking pages never show it. */
    protected $hidden = ['delivery_code'];

    protected $casts = ['cost' => 'decimal:2', 'delivered_at' => 'datetime', 'reschedule_for' => 'date:Y-m-d'];

    public function order()
    {
        return $this->belongsTo(Order::class)->withoutGlobalScopes();
    }

    public function events()
    {
        return $this->hasMany(ShipmentEvent::class)->orderBy('occurred_at')->orderBy('id');
    }

    public function isFinal(): bool
    {
        return in_array($this->status, self::FINAL, true);
    }
}
