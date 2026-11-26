<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderEvent extends Model
{
    protected $fillable = ['order_id', 'user_id', 'type', 'data'];

    protected $casts = ['data' => 'array'];

    protected static function booted(): void
    {
        // Every step of an order's life is announced on the event bus; notifications (and anything else) react to it.
        // Publishing is best-effort here: an order must never fail because an announcement could not be sent.
        static::created(function (self $event) {
            try {
                $order = Order::withoutGlobalScopes()->find($event->order_id);

                app(\App\Core\Events\EventBus::class)->publish(new \App\Core\Events\Event(
                    name: "order.{$event->type}",
                    payload: ['order_event_id' => $event->id, 'order_id' => $event->order_id, 'type' => $event->type, 'data' => $event->data],
                    tenantId: $order ? Company::withoutGlobalScopes()->whereKey($order->company_id)->value('tenant_id') : null,
                    aggregateType: 'order',
                    aggregateId: $event->order_id,
                    actorType: $event->user_id ? 'user' : null,
                    actorId: $event->user_id,
                ));
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function record(Order $order, string $type, ?User $by = null, array $data = []): self
    {
        return static::create([
            'order_id' => $order->id,
            'user_id' => $by?->id,
            'type' => $type,
            'data' => $data ?: null,
        ]);
    }
}
