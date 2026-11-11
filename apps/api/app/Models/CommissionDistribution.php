<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class CommissionDistribution extends Model
{
    protected $fillable = [
    'commission_id',
    'type',
    'company_id',
    'partner_id',
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

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    /** Platform admins see everything; others only their company's or partner's share. */
    public function scopeVisibleTo($query, User $user)
    {
        if ($user->hasRole('platform-admin')) {
            return $query;
        }

        return $query->where(function ($q) use ($user) {
            $q->whereRaw('1 = 0');

            if ($user->company_id) {
                $q->orWhere('company_id', $user->company_id);
            }

            if ($user->partner_id) {
                $q->orWhere('partner_id', $user->partner_id);
            }
        });
    }
}
