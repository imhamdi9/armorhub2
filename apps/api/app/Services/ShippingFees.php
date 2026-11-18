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

    /**
     * When a parcel ordered today should arrive: the market's usual delivery days, plus two days for a seller to pack it (goods ArmorHub keeps ship at once).
     * Null when the market has not said how long delivery takes.
     *
     * @return array{min_days: int, max_days: int, from: string, to: string}|null
     */
    public function estimate(?string $market, string $fulfilment = 'seller'): ?array
    {
        $eta = $this->rules($market)['eta_days'];
        if ($eta === null) {
            return null;
        }
        $max = $eta + ($fulfilment === 'armorhub' ? 0 : 2);

        return ['min_days' => $eta, 'max_days' => $max, 'from' => now()->addDays($eta)->toDateString(), 'to' => now()->addDays($max)->toDateString()];
    }

    /**
     * Faster delivery, when the market offers it: the extra fee for each seller's parcel and the days it takes.
     *
     * @return array{fee: float, eta_days: ?int}|null
     */
    public function express(?string $market): ?array
    {
        if (! $market) {
            return null;
        }
        $fee = $this->number("shipping.express_fee.{$market}");

        return $fee === null ? null : ['fee' => max(0.0, $fee), 'eta_days' => ($d = $this->number("shipping.express_eta_days.{$market}")) !== null && $d > 0 ? (int) $d : null];
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
