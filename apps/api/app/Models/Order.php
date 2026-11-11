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
        'company_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
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
}