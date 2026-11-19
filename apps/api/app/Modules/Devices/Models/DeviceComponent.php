<?php

namespace App\Modules\Devices\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceComponent extends Model
{
    protected $fillable = ['name', 'slug', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
