<?php

namespace App\Modules\Automotive\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;


class VehicleMake extends Model
{

    protected $fillable = [
        'name',
        'slug',
        'is_active',
    ];


    protected $casts = [
        'is_active' => 'boolean',
    ];


    public function models(): HasMany
    {
        return $this->hasMany(VehicleModel::class);
    }
}