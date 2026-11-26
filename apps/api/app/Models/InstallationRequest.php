<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A customer's ask for help fitting or repairing something. Centres see it anonymously; the customer's identity stays hidden until payment. */
class InstallationRequest extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['parts' => 'array', 'location_consent' => 'boolean', 'expires_at' => 'datetime', 'latitude' => 'float', 'longitude' => 'float',
            'paid_at' => 'datetime', 'started_at' => 'datetime', 'proof_submitted_at' => 'datetime', 'objection_deadline' => 'datetime', 'released_at' => 'datetime',
            'warranty_ends_at' => 'datetime', 'terms_accepted_at' => 'datetime', 'proxy_expires_at' => 'datetime',
            'price' => 'decimal:2', 'fee_amount' => 'decimal:2', 'centre_amount' => 'decimal:2', 'discount_amount' => 'decimal:2', 'pay_amount' => 'decimal:2', 'refund_amount' => 'decimal:2'];
    }

    public function offers()
    {
        return $this->hasMany(InstallationOffer::class, 'request_id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id')->withoutGlobalScopes();
    }

    public function selectedOffer()
    {
        return $this->belongsTo(InstallationOffer::class, 'selected_offer_id');
    }

    public function proofs()
    {
        return $this->hasMany(InstallationProof::class, 'request_id');
    }

    public function disputes()
    {
        return $this->hasMany(InstallationDispute::class, 'request_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['open', 'offers_received'], true) && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
