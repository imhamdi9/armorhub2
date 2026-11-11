<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use \App\Models\Concerns\Auditable;
    use SoftDeletes;


    protected $fillable = [
        'uuid',
        'name',
        'industry_id',
        'market',
        'domain',
        'status',
        'settings',
    ];

public function industry(): BelongsTo
{
    return $this->belongsTo(Industry::class);
}

public function companies(): HasMany
{
    return $this->hasMany(Company::class);
}
    protected $casts = [
        'settings' => 'array',
    ];


    protected static function booted(): void
    {
        static::creating(function ($tenant) {

            if (!$tenant->uuid) {
                $tenant->uuid = (string) Str::uuid();
            }

        });
    }
}