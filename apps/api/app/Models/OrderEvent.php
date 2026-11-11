<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderEvent extends Model
{
    protected $fillable = ['order_id', 'user_id', 'type', 'data'];

    protected $casts = ['data' => 'array'];

    protected static function booted(): void
    {
        static::created(fn (self $event) => app(\App\Services\OrderNotifier::class)->handle($event));
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
