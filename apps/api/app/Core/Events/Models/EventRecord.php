<?php

namespace App\Core\Events\Models;

use App\Core\Events\EventEnvelope;
use Illuminate\Database\Eloquent\Model;

class EventRecord extends Model
{
    protected $table = 'event_outbox';

    protected $guarded = [];

    protected $casts = ['payload' => 'array', 'occurred_at' => 'datetime'];

    public function deliveries()
    {
        return $this->hasMany(DeliveryRecord::class, 'event_id');
    }

    public function toEnvelope(): EventEnvelope
    {
        return new EventEnvelope(
            $this->event_id, $this->name, (int) $this->version, $this->tenant_id, $this->payload ?? [], $this->occurred_at, $this->correlation_id,
            $this->causation_id, $this->actor_type, $this->actor_id, $this->aggregate_type, $this->aggregate_id, $this->source,
        );
    }
}
