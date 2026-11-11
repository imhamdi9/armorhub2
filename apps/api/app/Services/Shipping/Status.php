<?php

namespace App\Services\Shipping;

use App\Models\Shipment;

/** Turns whatever words a carrier uses into the platform's shipment statuses. */
final class Status
{
    private const COMMON = [
        'created' => Shipment::CREATED, 'pending' => Shipment::CREATED, 'label_created' => Shipment::CREATED, 'booked' => Shipment::CREATED, 'picked_up' => Shipment::IN_TRANSIT,
        'in_transit' => Shipment::IN_TRANSIT, 'intransit' => Shipment::IN_TRANSIT, 'transit' => Shipment::IN_TRANSIT, 'shipped' => Shipment::IN_TRANSIT, 'dispatched' => Shipment::IN_TRANSIT,
        'out_for_delivery' => Shipment::OUT_FOR_DELIVERY, 'delivering' => Shipment::OUT_FOR_DELIVERY,
        'delivered' => Shipment::DELIVERED, 'completed' => Shipment::DELIVERED,
        'failed' => Shipment::FAILED, 'exception' => Shipment::FAILED, 'undelivered' => Shipment::FAILED, 'delivery_failed' => Shipment::FAILED,
        'returned' => Shipment::RETURNED, 'return_to_sender' => Shipment::RETURNED, 'rts' => Shipment::RETURNED,
        'cancelled' => Shipment::CANCELLED, 'canceled' => Shipment::CANCELLED,
    ];

    /**
     * @param  string  $custom  the admin's own mapping, e.g. "DLV=delivered, OFD=out_for_delivery" (matched case-insensitively)
     */
    public static function normalize(?string $raw, string $custom = ''): ?string
    {
        $key = strtolower(trim((string) $raw));
        if ($key === '') {
            return null;
        }

        foreach (explode(',', $custom) as $pair) {
            [$from, $to] = array_pad(array_map('trim', explode('=', $pair, 2)), 2, '');
            if ($from !== '' && strtolower($from) === $key && in_array($to, Shipment::STATUSES, true)) {
                return $to;
            }
        }

        $key = preg_replace('/[\s-]+/', '_', $key);

        return self::COMMON[$key] ?? null;
    }
}
