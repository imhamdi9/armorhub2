<?php

namespace App\Services;

use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Discount codes. A code takes a percentage or a fixed amount off the items of a purchase (never off delivery or the platform fee), the platform pays
 * for it, and every limit is checked again at checkout: the dates, the market, the smallest order, how often the code and the customer may use it.
 */
class PromoCodes
{
    public static function normalise(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', $code) ?? '');
    }

    /** @return array{code: PromoCode, discount: float} */
    public function check(string $code, User $user, float $itemsAmount, ?string $market, ?array $byProduct = null): array
    {
        $promo = PromoCode::where('code', self::normalise($code))->first();
        $fail = fn (string $why) => throw ValidationException::withMessages(['code' => [$why]]);

        // A seller's coupon counts only the products the seller chose for it.
        if ($promo?->company_id && $byProduct !== null) {
            $itemsAmount = (float) collect($promo->product_ids ?? [])->sum(fn ($id) => (float) ($byProduct[(int) $id] ?? 0.0));
            if ($itemsAmount <= 0) {
                $fail('This code is only for specific products, and none of them is in your cart.');
            }
        }

        if (! $promo || ! $promo->is_active) {
            $fail('This code is not valid.');
        }
        if (($promo->starts_at && $promo->starts_at->isFuture()) || ($promo->ends_at && $promo->ends_at->isPast())) {
            $fail('This code is not valid right now.');
        }
        if ($promo->market && $promo->market !== $market) {
            $fail('This code is not valid in your country.');
        }
        if ($promo->min_order && $itemsAmount < (float) $promo->min_order) {
            $fail('This code needs an order of at least '.rtrim(rtrim(number_format((float) $promo->min_order, 2, '.', ''), '0'), '.').'.');
        }
        $used = DB::table('promo_redemptions')->where('promo_code_id', $promo->id)->where('status', 'reserved');
        if ($promo->usage_limit !== null && (clone $used)->count() >= $promo->usage_limit) {
            $fail('This code has been fully used.');
        }
        if ((clone $used)->where('user_id', $user->id)->count() >= $promo->per_customer_limit) {
            $fail('You already used this code.');
        }

        $discount = $promo->type === 'percent' ? $itemsAmount * (float) $promo->value / 100 : (float) $promo->value;
        if ($promo->type === 'percent' && $promo->max_discount) {
            $discount = min($discount, (float) $promo->max_discount);
        }

        return ['code' => $promo, 'discount' => round(min($discount, $itemsAmount), 2)];
    }

    /** Splits a discount across the orders of a purchase in proportion to their totals, the rounding remainder landing on the last one. @param list<float> $totals @return list<float> */
    public function allocate(float $discount, array $totals): array
    {
        $sum = array_sum($totals);
        $out = [];
        $given = 0.0;
        foreach ($totals as $i => $t) {
            $share = $i === array_key_last($totals) ? round($discount - $given, 2) : round($discount * ($sum > 0 ? $t / $sum : 0), 2);
            $out[] = $share;
            $given += $share;
        }

        return $out;
    }
}
