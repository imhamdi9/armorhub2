<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One day of one campaign's spend, as the ad platform reported it. */
class AdSpend extends Model
{
    protected $table = 'ad_spend_daily';

    protected $guarded = [];

    protected $casts = ['day' => 'date', 'spend' => 'float'];

    /** "Eid Sale 2027" -> "eid-sale-2027": the form campaign links use, so spend and orders meet on the same name. */
    public static function key(string $name): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($name)), '-');
    }
}
