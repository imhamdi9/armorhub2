<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstallationMaterial extends Model
{
    protected $guarded = [];

    protected $casts = ['quantity' => 'decimal:2'];
}
