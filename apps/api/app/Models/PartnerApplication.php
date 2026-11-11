<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerApplication extends Model
{
    use Concerns\Auditable;

    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';

    protected $fillable = ['company_id', 'tenant_id', 'business_name', 'contact_name', 'email', 'email_canonical', 'phone', 'city', 'country',
        'description', 'latitude', 'longitude', 'password', 'status', 'reviewed_by', 'reviewed_at', 'rejection_reason', 'partner_id', 'user_id'];

    protected $hidden = ['password', 'email_canonical'];

    protected $casts = ['reviewed_at' => 'datetime'];

    public function store()
    {
        return $this->belongsTo(Company::class, 'company_id')->withoutGlobalScopes();
    }
}
