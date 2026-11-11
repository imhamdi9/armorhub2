<?php

namespace App\Models;

use App\Enums\QuoteStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class PartnerQuote extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'order_id',
        'partner_id',
        'price',
        'status',
        'company_id',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'status' => QuoteStatus::class,
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }
}