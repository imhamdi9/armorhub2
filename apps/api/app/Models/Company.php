<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;


class Company extends Model
{
    use SoftDeletes;


    protected $fillable = [
        'tenant_id',
        'uuid',
        'name',
        'legal_name',
        'email',
        'phone',
        'logo',
        'type',
        'status',
        'settings',
    ];


    protected $casts = [
        'settings' => 'array',
    ];


    protected static function booted(): void
    {
        static::creating(function ($company) {

            if (!$company->uuid) {
                $company->uuid = (string) Str::uuid();
            }

        });
    }


    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }


    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }
}