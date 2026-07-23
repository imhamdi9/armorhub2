<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommissionDistribution extends Model
{
    protected $fillable = [
    'commission_id',
    'type',
    'company_id',
    'installation_center_id',
    'amount',
    'status',
    'paid_at',
];

    public function commission()
    {
        return $this->belongsTo(Commission::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function installationCenter()
    {
        return $this->belongsTo(InstallationCenter::class);
    }
}