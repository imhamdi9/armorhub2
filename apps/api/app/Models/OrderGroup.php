<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderGroup extends Model
{
    public const AWAITING = 'awaiting_payment';
    public const PAID = 'paid';
    public const CANCELLED = 'cancelled';

    /** A group is paid through the payment providers under this id range (an installation job uses small negative ids). */
    public const PAYMENT_ID_OFFSET = 1_000_000_000;

    protected $fillable = ['company_id', 'customer_id', 'currency', 'items_amount', 'service_fee_amount', 'payable_amount', 'status', 'shipping_address', 'provider', 'transaction_id', 'paid_at'];

    protected $casts = ['items_amount' => 'decimal:2', 'service_fee_amount' => 'decimal:2', 'payable_amount' => 'decimal:2', 'shipping_address' => 'array', 'paid_at' => 'datetime'];

    protected $hidden = ['shipping_address'];

    public function orders()
    {
        return $this->hasMany(Order::class, 'group_id')->withoutGlobalScopes();
    }

    /** The id the payment providers know this group by. */
    public function paymentId(): int
    {
        return -(self::PAYMENT_ID_OFFSET + $this->id);
    }

    public static function fromPaymentId(int $orderId): ?int
    {
        return $orderId <= -self::PAYMENT_ID_OFFSET ? -$orderId - self::PAYMENT_ID_OFFSET : null;
    }
}
