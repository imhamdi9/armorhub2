<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CentreWithdrawal extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['account_number' => 'encrypted', 'amount' => 'decimal:2', 'processed_at' => 'datetime'];
    }
}
