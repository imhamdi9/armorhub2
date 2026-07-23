<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class InstallationCenter extends Model
{

    protected $fillable = [

        'company_id',
        'name',
        'country',
        'city',
        'address',
        'phone',
        'is_active',

    ];


    protected $casts = [
        'is_active'=>'boolean'
    ];


    public function company()
    {
        return $this->belongsTo(Company::class);
    }


    public function quotes()
    {
        return $this->hasMany(InstallationQuote::class);
    }

}