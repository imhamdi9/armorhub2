<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    protected $fillable = ['tenant_id', 'name', 'city', 'address', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
