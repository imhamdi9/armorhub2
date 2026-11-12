<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstallationProof extends Model
{
    protected $guarded = [];

    protected $casts = ['taken_at' => 'datetime', 'flagged' => 'boolean', 'latitude' => 'float', 'longitude' => 'float'];
}
