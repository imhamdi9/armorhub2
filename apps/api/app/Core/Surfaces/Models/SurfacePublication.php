<?php

namespace App\Core\Surfaces\Models;

use Illuminate\Database\Eloquent\Model;

class SurfacePublication extends Model
{
    protected $guarded = [];

    protected $casts = ['publish_at' => 'datetime', 'decided_at' => 'datetime'];
}
