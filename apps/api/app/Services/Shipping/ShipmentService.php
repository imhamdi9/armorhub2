<?php

namespace App\Services\Shipping;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Books parcels through a provider and keeps each shipment's status and history up to date. */
class ShipmentService
{
    /** A status may only move forward; a failed delivery can be retried. */
    private const RANK = [Shipment::CREATED => 0, Shipment::IN_TRANSIT => 1, Shipment::FAILED => 2, Shipment::OUT_FOR_DELIVERY => 2, Shipment::DELIVERED => 3, Shipment::RETURNED => 3, Shipment::CANCELLED => 3];

    public function __construct(private ShippingProviders $providers)
    {
    }

    /** Orders whose goods exist to be sent: paid, for a physical product, with an address. */
    public function assertShippable(Order $order): void
    {
        $order->loadMissing('product');

        if (! $order->product?->requires_shipping) {
            throw ValidationException::withMessages(['order' => ['This order does not need shipping.']]);
        }
        if (! in_array($order->status, [OrderStatus::Paid, OrderStatus::AppointmentScheduled, OrderStatus::ServiceInProgress, OrderStatus::Completed], true)) {
            throw ValidationException::withMessages(['order' => ['The order must be paid before it can be shipped.']]);
        }
        if (empty($order->shipping_address)) {
            throw ValidationException::withMessages(['order' => ['The order has no delivery address.']]);
        }
    }

    /** @return list<array<string, mixed>> */
    public function quote(Order $order, string $providerKey): array
    {
        $this->assertShippable($order);
        $provider = $this->usable($providerKey);

        return $provider->quote($order, $order->shipping_address, $this->providers->config($providerKey));
    }

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ShippingUnavailable
     */
    public function create(Order $order, ?User $by, string $providerKey, array $input = []): Shipment
    {
        $provider = $this->usable($providerKey);

        return DB::transaction(function () use ($order, $by, $provider, $providerKey, $input) {
            $locked = Order::withoutGlobalScopes()->lockForUpdate()->findOrFail($order->id);
            $this->assertShippable($locked);

            // One live parcel per order; a failed, returned or cancelled one can be replaced.
            if (Shipment::where('order_id', $locked->id)->whereNotIn('status', [Shipment::CANCELLED, Shipment::RETURNED])->exists()) {
                throw ValidationException::withMessages(['order' => ['This order already has a shipment.']]);
            }

            $made = $provider->create($locked, $locked->shipping_address, $input, $this->providers->config($providerKey));

            $shipment = Shipment::create([
                'order_id' => $locked->id, 'provider' => $providerKey, 'carrier' => $made['carrier'] ?? null, 'service' => $input['service'] ?? null,
                'reference' => $made['reference'] ?? null, 'tracking_number' => $made['tracking_number'] ?? null, 'tracking_url' => $made['tracking_url'] ?? null,
                'label_url' => $made['label_url'] ?? null, 'status' => Shipment::CREATED, 'cost' => $made['cost'] ?? null,
                'currency' => $locked->currency, 'created_by' => $by?->id, 'delivery_code' => (string) random_int(1000, 9999),
            ]);
            ShipmentEvent::create(['shipment_id' => $shipment->id, 'status' => Shipment::CREATED, 'description' => 'Shipment booked', 'occurred_at' => now(), 'dedupe_key' => 'created']);

            OrderEvent::record($locked, 'shipped', $by, ['carrier' => $shipment->carrier, 'tracking_number' => $shipment->tracking_number]);

            return $shipment->load('events');
        });
    }

    /** Records one tracking update. Returns true when it changed anything. */
    public function apply(Shipment $shipment, TrackingEvent $event, ?User $by = null): bool
    {
        if (! in_array($event->status, Shipment::STATUSES, true)) {
            return false;
        }

        return DB::transaction(function () use ($shipment, $event, $by) {
            $locked = Shipment::lockForUpdate()->findOrFail($shipment->id);
            $at = $event->occurredAt ?? now();
            $key = sha1($event->status.'|'.$at->getTimestamp().'|'.$event->description);

            $stored = ShipmentEvent::firstOrCreate(['shipment_id' => $locked->id, 'dedupe_key' => $key],
                ['status' => $event->status, 'description' => $event->description, 'location' => $event->location, 'occurred_at' => $at]);

            if (! $stored->wasRecentlyCreated || $locked->isFinal() || (self::RANK[$event->status] < self::RANK[$locked->status] && $locked->status !== Shipment::FAILED)) {
                return $stored->wasRecentlyCreated;
            }

            if ($event->status !== $locked->status) {
                $locked->update(['status' => $event->status, 'delivered_at' => $event->status === Shipment::DELIVERED ? $at : $locked->delivered_at]);
                if ($event->status === Shipment::FAILED) {
                    $locked->update(['attempts' => $locked->attempts + 1, 'failed_reason' => $event->description ? mb_substr($event->description, 0, 250) : null]);
                }

                $type = match ($event->status) {
                    Shipment::OUT_FOR_DELIVERY => 'shipment_out_for_delivery', Shipment::DELIVERED => 'shipment_delivered', Shipment::FAILED => 'shipment_failed',
                    Shipment::RETURNED => 'shipment_returned', default => null,
                };
                if ($type) {
                    OrderEvent::record($locked->order, $type, $by, ['tracking_number' => $locked->tracking_number, 'reason' => $locked->failed_reason, 'attempts_left' => max(0, Shipment::MAX_ATTEMPTS - $locked->attempts)]);
                }

                // A seller's product is complete once it is delivered (there is no job for a partner to finish).
                if ($event->status === Shipment::DELIVERED && $locked->order->product?->seller_id !== null && $locked->order->status === OrderStatus::Paid) {
                    $locked->order->update(['status' => OrderStatus::Completed, 'completed_at' => $at]);
                }

                // Goods that arrived earn points (and may pay a referral reward).
                if ($event->status === Shipment::DELIVERED) {
                    app(\App\Services\Loyalty::class)->goodsDelivered($locked->order);
                }

                // Pay on delivery: the cash was collected at the door, so the payment is complete now.
                if ($event->status === Shipment::DELIVERED) {
                    $due = \App\Models\Payment::withoutGlobalScopes()->where('order_id', $locked->order_id)->where('status', 'cod_due')->first();
                    if ($due) {
                        $due->update(['status' => 'completed']);
                        app(\App\Services\Ledger\LedgerPostings::class)->codCollected($locked->order);
                    }
                }
            }

            return true;
        });
    }

    /**
     * After a failed attempt the customer picks another day. The parcel goes back on its way and the people who ship it are told.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function reschedule(Shipment $shipment, string $date, ?string $note, User $by): Shipment
    {
        if ($shipment->status !== Shipment::FAILED) {
            throw ValidationException::withMessages(['shipment' => ['Only a delivery that failed can be rescheduled.']]);
        }
        if ($shipment->attempts >= Shipment::MAX_ATTEMPTS) {
            throw ValidationException::withMessages(['shipment' => ['There were too many failed attempts. Please contact support.']]);
        }

        $this->apply($shipment, new TrackingEvent(Shipment::IN_TRANSIT, $shipment->tracking_number, $shipment->reference, "Delivery rescheduled for {$date} (after attempt {$shipment->attempts})", null, now()), $by);
        $shipment->update(['reschedule_for' => $date, 'reschedule_note' => $note ? mb_substr($note, 0, 200) : null]);
        OrderEvent::record($shipment->order, 'delivery_rescheduled', $by, ['date' => $date, 'note' => $note, 'tracking_number' => $shipment->tracking_number]);

        return $shipment->fresh('events');
    }

    /** Pull the carrier's latest tracking and apply it. Returns how many updates were new. */
    public function refresh(Shipment $shipment): int
    {
        $key = $shipment->provider;
        if (! $this->providers->has($key)) {
            return 0;
        }

        $new = 0;
        foreach ($this->providers->get($key)->track($shipment, $this->providers->config($key)) as $event) {
            $new += $this->apply($shipment->fresh(), $event) ? 1 : 0;
        }

        return $new;
    }

    private function usable(string $key): ShippingProvider
    {
        if (! $this->providers->has($key)) {
            throw ValidationException::withMessages(['provider' => ['Unknown shipping provider.']]);
        }
        if (! $this->providers->isConfigured($key)) {
            throw new ShippingUnavailable('This shipping provider is not configured yet.');
        }

        return $this->providers->get($key);
    }
}
