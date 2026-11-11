<?php

namespace App\Models;

use App\Modules\Contracts\IndustryModule;
use App\Modules\IndustryRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Industry extends Model
{
    protected $fillable = [
        'key',
        'name',
        'description',
        'settings',
        'is_active',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
    ];

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** The code module backing this industry, if one is registered. */
    public function module(): ?IndustryModule
    {
        $registry = app(IndustryRegistry::class);

        return $registry->has($this->key) ? $registry->get($this->key) : null;
    }
}
