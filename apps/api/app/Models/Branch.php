<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;


class Branch extends Model
{
    use SoftDeletes;


    protected $fillable = [
        'company_id',
        'uuid',
        'name',
        'address',
        'city',
        'country',
        'latitude',
        'longitude',
        'working_hours',
        'is_active',
    ];


    protected $casts = [
        'working_hours' => 'array',
        'is_active' => 'boolean',
    ];


    protected static function booted(): void
    {
        static::creating(function ($branch) {

            if (!$branch->uuid) {
                $branch->uuid = (string) Str::uuid();
            }

        });
    }


    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}