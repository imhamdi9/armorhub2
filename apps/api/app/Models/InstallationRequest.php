<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A customer's ask for help fitting or repairing something. Centres see it anonymously; the customer's identity stays hidden until payment. */
class InstallationRequest extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['parts' => 'array', 'location_consent' => 'boolean', 'expires_at' => 'datetime', 'latitude' => 'float', 'longitude' => 'float'];
    }

    public function offers()
    {
        return $this->hasMany(InstallationOffer::class, 'request_id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id')->withoutGlobalScopes();
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['open', 'offers_received'], true) && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
