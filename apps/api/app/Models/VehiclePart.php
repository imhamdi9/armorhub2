<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class VehiclePart extends Model
{

    protected $fillable = [
        'name',
        'slug',
        'description',
        'coverage_factor',
        'is_active',
    ];


    protected $casts = [
        'coverage_factor'=>'decimal:2',
        'is_active'=>'boolean',
    ];

}