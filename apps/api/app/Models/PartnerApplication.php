<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerApplication extends Model
{
    use Concerns\Auditable;

    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';

    protected $fillable = ['type', 'registration_number', 'tax_number', 'company_id', 'tenant_id', 'business_name', 'contact_name', 'email', 'email_canonical', 'phone', 'city', 'country',
        'description', 'latitude', 'longitude', 'password', 'status', 'reviewed_by', 'reviewed_at', 'rejection_reason', 'partner_id', 'user_id', 'plan_key', 'plan_pay_token', 'plan_payment_reference', 'plan_paid_at'];

    protected $hidden = ['password', 'email_canonical', 'plan_pay_token'];

    protected $casts = ['reviewed_at' => 'datetime', 'plan_paid_at' => 'datetime'];

    public function documents()
    {
        return $this->hasMany(ApplicationDocument::class, 'application_id');
    }

    public function store()
    {
        return $this->belongsTo(Company::class, 'company_id')->withoutGlobalScopes();
    }
}
