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
     'public_profile',
     'description',
     'logo_url',
    ];


    protected $casts = [
        'is_active'=>'boolean',
        'public_profile'=>'boolean',
    ];


    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function quotes()
    {
        return $this->hasMany(
            PartnerQuote::class
        );
    }
}