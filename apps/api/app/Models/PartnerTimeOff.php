<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerTimeOff extends Model
{
    protected $table = 'partner_time_off';

    protected $fillable = ['partner_id', 'date', 'reason'];

    protected $casts = ['date' => 'date'];
}
