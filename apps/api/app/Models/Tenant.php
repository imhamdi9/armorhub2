<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Tenant extends Model
{
    use SoftDeletes;


    protected $fillable = [
        'uuid',
        'name',
        'domain',
        'status',
        'settings',
    ];


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