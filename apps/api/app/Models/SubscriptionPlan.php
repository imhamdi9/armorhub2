<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A monthly plan for partners and sellers: how many customers a month, what commission, how many team seats. */
class SubscriptionPlan extends Model
{
    protected $fillable = ['key', 'name', 'price_monthly', 'currency', 'customer_limit', 'commission_percentage', 'team_seats', 'is_active', 'sort'];

    protected function casts(): array
    {
        return ['price_monthly' => 'float', 'commission_percentage' => 'float', 'customer_limit' => 'integer', 'team_seats' => 'integer', 'is_active' => 'boolean'];
    }
}
