<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerPayout extends Model
{
    use Concerns\Auditable;

    protected $guarded = [];

    protected $casts = ['amount' => 'decimal:2', 'paid_at' => 'datetime'];

    public function company()
    {
        return $this->belongsTo(Company::class)->withoutGlobalScopes();
    }
}
