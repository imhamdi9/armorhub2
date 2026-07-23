<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class Order extends Model
{
    protected $fillable = [

        'customer_id',
        'film_id',
        'vehicle_model_id',
        'status',
        'film_amount',
        'installation_amount',
        'commission_amount',
        'total_amount',
        'currency',

    ];


    public function customer()
    {
        return $this->belongsTo(User::class);
    }


    public function film()
    {
        return $this->belongsTo(Film::class);
    }


    public function vehicleModel()
    {
        return $this->belongsTo(VehicleModel::class);
    }


    public function quotes()
    {
        return $this->hasMany(InstallationQuote::class);
    }
}