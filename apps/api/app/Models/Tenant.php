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

/** The industries this market runs (cars, electronics, beauty...). */
public function industries(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
{
    return $this->belongsToMany(Industry::class, 'tenant_industries')->withTimestamps();
}

/** @return list<int> ids of every industry of this market (the single legacy industry counts too) */
public function industryIds(): array
{
    return collect($this->industries()->pluck('industries.id')->all())->when($this->industry_id, fn ($c) => $c->push($this->industry_id))->unique()->values()->all();
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