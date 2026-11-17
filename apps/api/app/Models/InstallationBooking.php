<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstallationBooking extends Model
{
    protected $guarded = [];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime'];

    public function request()
    {
        return $this->belongsTo(InstallationRequest::class, 'request_id');
    }
}
