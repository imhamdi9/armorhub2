<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    protected $fillable = ['key', 'name_en', 'name_ar', 'scope', 'tenant_id'];

    protected $casts = ['scope' => 'array'];

    public function members(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** Roles of this department are named "dept-<key>-<role>" (market departments: "dept-t<market>-<key>-<role>"). */
    public function rolePrefix(): string
    {
        return $this->tenant_id ? "dept-t{$this->tenant_id}-{$this->key}-" : "dept-{$this->key}-";
    }
}
