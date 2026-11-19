<?php

namespace App\Core\Blueprints\Models;

use Illuminate\Database\Eloquent\Model;

class BlueprintApplication extends Model
{
    protected $guarded = [];

    protected $casts = ['params' => 'array', 'outputs' => 'array'];
}
