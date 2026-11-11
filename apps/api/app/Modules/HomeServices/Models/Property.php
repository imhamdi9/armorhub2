<?php

namespace App\Modules\HomeServices\Models;

use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    protected $fillable = ['customer_id', 'type', 'address', 'city', 'country', 'area_sqm'];

    protected $casts = ['area_sqm' => 'decimal:2'];
}
