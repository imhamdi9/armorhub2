<?php

namespace App\Services;

use App\Support\Settings\Settings;

/**
 * What delivery costs the customer, set per market by the administrator: a flat fee for each seller's parcel, free once that seller's part of the
 * cart reaches a threshold, plus how many days delivery usually takes. With no fee set, delivery is free (as it was before this existed).
 */
class ShippingFees
{
    public function __construct(private Settings $settings)
    {
    }

    /** @return array{flat: float, free_over: ?float, eta_days: ?int} */
    public function rules(?string $market): array
    {
        if (! $market) {
            return ['flat' => 0.0, 'free_over' => null, 'eta_days' => null];
        }
        $flat = $this->number("shipping.flat_fee.{$market}");
        $over = $this->number("shipping.free_over.{$market}");
        $eta = $this->number("shipping.eta_days.{$market}");

        return ['flat' => max(0.0, $flat ?? 0.0), 'free_over' => $over !== null && $over > 0 ? $over : null, 'eta_days' => $eta !== null && $eta > 0 ? (int) $eta : null];
    }

    /** The fee for one seller's part of a purchase. */
    public function forSeller(?string $market, float $sellerSubtotal): float
    {
        $r = $this->rules($market);
        if ($r['flat'] <= 0 || ($r['free_over'] !== null && $sellerSubtotal >= $r['free_over'])) {
            return 0.0;
        }

        return round($r['flat'], 2);
    }

    private function number(string $key): ?float
    {
        try {
            $v = $this->settings->get($key);
        } catch (\InvalidArgumentException) {
            return null;
        }

        return $v === null || $v === '' ? null : (float) $v;
    }
}
