<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderMessage extends Model
{
    protected $guarded = [];

    protected $casts = ['handled_at' => 'datetime'];
}
