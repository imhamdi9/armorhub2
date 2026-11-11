<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'is_secret', 'updated_by'];

    protected $casts = ['is_secret' => 'boolean'];

    // Secrets must never leak through a stray toArray()/JSON response.
    protected $hidden = ['value'];
}
