<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class InstallationQuote extends Model
{

    protected $fillable = [

        'order_id',
        'installation_center_id',
        'price',
        'status',

    ];


    public function order()
    {
        return $this->belongsTo(Order::class);
    }


    public function center()
    {
        return $this->belongsTo(
            InstallationCenter::class,
            'installation_center_id'
        );
    }

}