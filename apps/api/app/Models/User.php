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

class User extends Authenticatable implements \Illuminate\Contracts\Auth\MustVerifyEmail
{
    use \App\Models\Concerns\Auditable;
    use HasApiTokens;
    use HasFactory;
    use Notifiable;
    use HasRoles;


    protected $fillable = [
      'name',
      'email',
        'phone',
      'password',
      'is_active',
      'email_verified_at',
      'company_id',
      'partner_id',
      'latitude',
      'longitude',
      'city',
      'country',
      'location_enabled',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $user) {
            $user->email = mb_strtolower(trim($user->email));
            $user->email_canonical = \App\Support\Security\EmailPolicy::canonical($user->email);
        });
    }

    /** The first industry this user's market runs, if any (kept for code that thinks in one industry). */
    public function industryId(): ?int
    {
        return $this->industryIds()[0] ?? null;
    }

    /** @return list<int> every industry of this user's market */
    public function industryIds(): array
    {
        return $this->company?->tenant?->industryIds() ?? [];
    }

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