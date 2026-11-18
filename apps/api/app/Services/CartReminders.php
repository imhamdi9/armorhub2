<?php

namespace App\Services;

use App\Models\Cart;
use App\Notifications\OrderUpdate;
use App\Support\Settings\Settings;
use Illuminate\Support\Facades\Lang;

/**
 * A cart left alone for a while gets one gentle reminder (in the customer's language, by app notice, push and e-mail unless they switched those off).
 * A cart is reminded once per time it was touched: adding something new later makes it eligible again. Carts older than a week are let go.
 */
class CartReminders
{
    public const GIVE_UP_DAYS = 7;

    public function __construct(private Settings $settings)
    {
    }

    /** @return int how many customers were reminded */
    public function run(): int
    {
        $hours = (int) $this->settings->get('loyalty.cart_reminder_hours');
        if ($hours <= 0) {
            return 0;
        }

        $sent = 0;
        Cart::query()->with(['items', 'customer'])->whereHas('items')->get()->each(function (Cart $cart) use ($hours, &$sent) {
            $touched = $cart->items->max('updated_at');
            $user = $cart->customer;
            if (! $touched || ! $user || ! $user->is_active || $touched->gt(now()->subHours($hours)) || $touched->lt(now()->subDays(self::GIVE_UP_DAYS))) {
                return;
            }
            if ($cart->reminded_at && $cart->reminded_at->gte($touched)) {
                return;                                                                                       // already reminded about this cart
            }

            $locale = $user->preferredLocale() ?? 'en';
            $count = $cart->items->sum('quantity');
            $title = Lang::get('You left something in your cart', [], $locale);
            $body = Lang::get('{n} item(s) are waiting for you. Finish your order before they sell out.', ['n' => $count], $locale);
            // The strings use {n}; Laravel's translator uses :n, so fill it in ourselves.
            $body = str_replace('{n}', (string) $count, $body);

            $user->notify((new OrderUpdate(0, 'cart_reminder', $title, $body, '/dashboard/cart'))->locale($locale));
            $cart->forceFill(['reminded_at' => now()])->save();
            $sent++;
        });

        return $sent;
    }
}
