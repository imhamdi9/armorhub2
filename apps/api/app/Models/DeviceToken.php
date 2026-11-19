<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceToken extends Model
{
    protected $fillable = ['user_id', 'platform', 'token', 'last_seen_at'];

    protected $casts = ['last_seen_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
