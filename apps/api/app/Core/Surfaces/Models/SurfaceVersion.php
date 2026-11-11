<?php

namespace App\Core\Surfaces\Models;

use Illuminate\Database\Eloquent\Model;

class SurfaceVersion extends Model
{
    protected $guarded = [];

    protected $casts = ['content' => 'array', 'published_at' => 'datetime'];
}
