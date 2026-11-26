<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductFitment extends Model
{
    protected $fillable = ['product_id', 'vehicle_make_id', 'vehicle_model_id', 'year_from', 'year_to', 'note'];
}
