<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class FilmPriceRule extends Model
{

    protected $fillable = [
        'film_id',
        'vehicle_part_id',
        'price',
        'installation_minutes',
        'is_active',
    ];


    protected $casts = [
        'price'=>'decimal:2',
        'is_active'=>'boolean',
    ];


    public function film(): BelongsTo
    {
        return $this->belongsTo(Film::class);
    }


    public function vehiclePart(): BelongsTo
    {
        return $this->belongsTo(
            VehiclePart::class
        );
    }

}