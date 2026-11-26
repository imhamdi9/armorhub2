<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerWorkingHour extends Model
{
    protected $fillable = ['partner_id', 'weekday', 'opens', 'closes'];
}
