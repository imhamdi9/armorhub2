<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstallationOffer extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'distance_km' => 'float'];
    }

    public function request()
    {
        return $this->belongsTo(InstallationRequest::class, 'request_id');
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class)->withoutGlobalScopes();
    }
}
