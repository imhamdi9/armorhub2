<?php

namespace App\Modules\Automotive\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class VehicleYear extends Model
{

    protected $fillable = [
        'vehicle_model_id',
        'year',
        'is_active',
    ];


    protected $casts = [
        'is_active'=>'boolean',
    ];


    public function model(): BelongsTo
    {
        return $this->belongsTo(
            VehicleModel::class,
            'vehicle_model_id'
        );
    }
}