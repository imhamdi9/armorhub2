<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VehicleModel extends Model
{
    protected $fillable = [
        'vehicle_make_id',
        'name',
        'slug',
        'is_active',
    ];


    protected $casts = [
        'is_active' => 'boolean',
    ];

public function years(): HasMany
{
    return $this->hasMany(
        VehicleYear::class
    );
}

    public function make(): BelongsTo
    {
        return $this->belongsTo(
            VehicleMake::class,
            'vehicle_make_id'
        );
    }
}