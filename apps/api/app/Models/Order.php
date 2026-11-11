<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Models\Commission;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Product;
use App\Models\PartnerQuote;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'customer_id',
        'product_id',
        'subject_type',
        'subject_id',
        'status',
        'product_amount',
        'service_amount',
        'commission_amount',
        'total_amount',
        'currency',
        'market',
        'tax_rate',
        'tax_amount',
        'company_id',
        'shipping_address',
        'scheduled_at',
        'started_at',
        'completed_at',
        'cancelled_at',
    ];

    /** The delivery address is only sent to the customer and the staff handling the parcel. */
    protected $hidden = ['shipping_address'];

    protected function casts(): array
    {
        return [
            'shipping_address' => 'array',
            'status' => OrderStatus::class,
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function commission()
    {
        return $this->hasOne(Commission::class);
    }

    /** The thing being serviced (vehicle, property, device...), if any. */
    public function subject()
    {
        return $this->morphTo();
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function partnerQuotes()
    {
        return $this->hasMany(PartnerQuote::class);
    }

    public function events()
    {
        return $this->hasMany(OrderEvent::class)->orderBy('id');
    }

    public function warranty()
    {
        return $this->hasOne(Warranty::class);
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }
}
