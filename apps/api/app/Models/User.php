<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use App\Models\Company;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Partner;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;
    use HasRoles;


    protected $fillable = [
      'name',
      'email',
      'password',
      'is_active',
      'company_id',
      'partner_id',
      'latitude',
      'longitude',
      'city',
      'country',
      'location_enabled',
    ];
    
    public function company(): BelongsTo
    {
    return $this->belongsTo(Company::class);
    }

    public function partner()
    {
    return $this->belongsTo(Partner::class);
    }


    protected $hidden = [
        'password',
        'remember_token',
    ];


    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
}