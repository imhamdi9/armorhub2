<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompany;

class Partner extends Model
{
    use \App\Models\Concerns\Auditable;
    use BelongsToCompany;

    protected $fillable = [
     'company_id',
     'name',
     'country',
     'city',
     'address',
     'phone',
     'is_active',
     'latitude',
     'longitude',
     'location_enabled',
    ];


    protected $casts = [
        'is_active'=>'boolean'
    ];


    public function quotes()
    {
        return $this->hasMany(
            PartnerQuote::class
        );
    }
}