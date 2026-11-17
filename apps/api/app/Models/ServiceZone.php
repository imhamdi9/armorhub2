<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceZone extends Model
{
    protected $fillable = ['market', 'key', 'name_en', 'name_ar', 'city', 'latitude', 'longitude', 'radius_km', 'is_active', 'position'];

    protected $casts = ['is_active' => 'boolean', 'latitude' => 'float', 'longitude' => 'float'];
}
