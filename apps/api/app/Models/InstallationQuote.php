<?php

namespace App\Models;

use App\Enums\QuoteStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class InstallationQuote extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'order_id',
        'installation_center_id',
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

    public function installationCenter()
    {
        return $this->belongsTo(InstallationCenter::class);
    }
}