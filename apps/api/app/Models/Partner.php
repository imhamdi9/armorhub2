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
     'industry_id',
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
     'service_radius_km',
     'daily_capacity',
     'bays',
     'job_hours',
     'min_job_price',
     'accepts_requests',
     'work_types',
    ];


    protected $casts = [
        'is_active'=>'boolean',
        'public_profile'=>'boolean',
        'accepts_requests'=>'boolean',
        'work_types'=>'array',
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