<?php

namespace App\Services;

use App\Models\LoyaltyEntry;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\User;
use App\Support\Settings\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * What keeps a customer coming back: points for goods that arrived, the right to spend them on the next purchase, and a reward for both people
 * when a friend's first order arrives. Every payout has a unique reference, so a replayed event can never pay twice.
 */
class Loyalty
{
    public function __construct(private Reputation $points, private Settings $settings)
    {
    }

    // ---------------------------------------------------------------- earning

    /** Points for goods that were delivered; also pays the referral reward the first time a referred customer receives an order. */
    public function goodsDelivered(Order $order): void
    {
        if (! $order->customer_id) {
            return;
        }
        // Money already given back as a partial refund earns nothing.
        $kept = $order->partial_refund_status && (float) $order->payable_amount > 0 ? max(0.0, 1 - (float) $order->partial_refund_amount / (float) $order->payable_amount) : 1.0;
        $earned = (int) floor((float) $order->total_amount * $kept * (float) $this->settings->get('loyalty.goods_points_per_unit'));
        $this->grant((int) $order->customer_id, $earned, 'goods_order', "order:{$order->id}");

        $buyer = User::find($order->customer_id);
        if ($buyer?->referred_by && User::whereKey($buyer->referred_by)->exists()) {
            $reward = (int) $this->settings->get('loyalty.referral_points');
            // Both people are rewarded once, for the friend's first delivered order only.
            if ($this->grant($buyer->id, $reward, 'referral', "referral:{$buyer->id}")) {
                $this->grant((int) $buyer->referred_by, $reward, 'referral', "referral:{$buyer->id}");
            }
        }
    }

    /** @return bool whether it was new (false when this reference was already paid) */
    private function grant(int $userId, int $points, string $reason, string $ref): bool
    {
        if ($points === 0) {
            return false;
        }

        return LoyaltyEntry::firstOrCreate(['user_id' => $userId, 'ref' => $ref], ['points' => $points, 'reason' => $reason])->wasRecentlyCreated;
    }

    /**
     * An order was refunded: points earned on it are taken back, and when every order of its purchase has been refunded the points spent on
     * that purchase come back. (Each step has its own reference, so running it twice changes nothing.)
     */
    public function refunded(Order $order): void
    {
        $earned = LoyaltyEntry::where('user_id', $order->customer_id)->where('ref', "order:{$order->id}")->first();
        if ($earned) {
            $this->grant((int) $order->customer_id, -1 * (int) $earned->points, 'refunded', "refund:order:{$order->id}");
        }
        $group = $order->group_id ? OrderGroup::find($order->group_id) : null;
        if ($group && ! Order::withoutGlobalScopes()->where('group_id', $group->id)->whereNull('refund_status')->exists()) {
            $this->release($group);
        }
    }

    /**
     * A goodwill gesture when something went wrong but a refund is not the answer: points straight into the customer's balance.
     * The total for one order is capped (setting), and every gesture keeps who gave it and why.
     *
     * @return int the points given
     */
    public function goodwill(Order $order, int $points, string $why, User $by): int
    {
        $max = (int) $this->settings->get('loyalty.goodwill_max_points');
        if (! $order->customer_id) {
            throw \Illuminate\Validation\ValidationException::withMessages(['order' => ['A guest order has no points balance. Refund part of it by hand instead.']]);
        }

        return DB::transaction(function () use ($order, $points, $why, $by, $max) {
            // Lock the customer's row so two agents cannot both slip under the cap at once.
            User::withoutGlobalScopes()->whereKey($order->customer_id)->lockForUpdate()->first();
            $given = (int) LoyaltyEntry::where('user_id', $order->customer_id)->where('reason', 'goodwill')->where('ref', 'like', "goodwill:order:{$order->id}:%")->sum('points');
            if ($points < 1 || $given + $points > $max) {
                throw \Illuminate\Validation\ValidationException::withMessages(['points' => ["At most {$max} points can be given for one order ({$given} already given)."]]);
            }
            LoyaltyEntry::create(['user_id' => $order->customer_id, 'points' => $points, 'reason' => 'goodwill', 'ref' => "goodwill:order:{$order->id}:".Str::random(8)]);
            \App\Support\Audit::record('orders.goodwill', $order, ['points' => $points, 'why' => $why], $by);

            return $points;
        });
    }

    /** Part of an order was refunded: the same share of the points it already earned is taken back (once). */
    public function partlyRefunded(Order $order): void
    {
        $earned = LoyaltyEntry::where('user_id', $order->customer_id)->where('ref', "order:{$order->id}")->first();
        $r = (float) $order->payable_amount > 0 ? (float) $order->partial_refund_amount / (float) $order->payable_amount : 0.0;
        if ($earned && $r > 0) {
            $this->grant((int) $order->customer_id, -1 * (int) floor((int) $earned->points * $r), 'refunded', "refund:partial:order:{$order->id}");
        }
    }

    // ---------------------------------------------------------------- spending

    /** What a customer may use on a purchase worth $items: their balance, capped at a share of the purchase. @return array{balance: int, max_use: int, value: float} */
    public function redeemable(User $user, float $items): array
    {
        $balance = max(0, $this->points->points($user->id));
        $value = $this->points->pointValue();
        $cap = $value > 0 ? (int) floor($items * (float) $this->settings->get('loyalty.max_redeem_percent') / 100 / $value) : 0;

        return ['balance' => $balance, 'max_use' => max(0, min($balance, $cap)), 'value' => $value];
    }

    /** The money value of this many points. */
    public function worth(int $points): float
    {
        return round($points * $this->points->pointValue(), 2);
    }

    /** Spends points on a purchase. */
    public function spend(User $user, int $points, OrderGroup $group): void
    {
        if ($points > 0) {
            LoyaltyEntry::create(['user_id' => $user->id, 'points' => -$points, 'reason' => 'redeemed_goods', 'ref' => "redeem:group:{$group->id}"]);
        }
    }

    /** The purchase was dropped before payment: the points come back. */
    public function release(OrderGroup $group): void
    {
        $spent = LoyaltyEntry::where('user_id', $group->customer_id)->where('ref', "redeem:group:{$group->id}")->first();
        if ($spent) {
            $this->grant((int) $group->customer_id, -1 * (int) $spent->points, 'released', "release:group:{$group->id}");
        }
    }

    // ---------------------------------------------------------------- referrals

    /** The customer's own code, made the first time it is needed. */
    public function codeOf(User $user): string
    {
        if (! $user->referral_code) {
            do {
                $code = strtoupper(Str::random(8));
            } while (User::where('referral_code', $code)->exists());
            $user->forceFill(['referral_code' => $code])->save();
        }

        return $user->referral_code;
    }

    /** @return array{code: string, link: string, invited: int, rewarded: int, points_each: int} */
    public function referral(User $user): array
    {
        $code = $this->codeOf($user);

        return [
            'code' => $code, 'link' => rtrim((string) config('platform.web_url'), '/').'/register?ref='.$code,
            'invited' => User::where('referred_by', $user->id)->count(),
            'rewarded' => LoyaltyEntry::where('user_id', $user->id)->where('reason', 'referral')->count(),
            'points_each' => (int) $this->settings->get('loyalty.referral_points'),
        ];
    }

    /** The person a signup code belongs to, or null (never the person signing up). */
    public function owner(?string $code): ?User
    {
        $code = strtoupper(trim((string) $code));

        return $code === '' ? null : User::where('referral_code', $code)->first();
    }
}
