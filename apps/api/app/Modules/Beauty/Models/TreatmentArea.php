<?php

namespace App\Modules\Beauty\Models;

use Illuminate\Database\Eloquent\Model;

class TreatmentArea extends Model
{
    protected $fillable = ['name', 'slug', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
