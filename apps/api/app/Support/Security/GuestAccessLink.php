<?php

namespace App\Support\Security;

use App\Models\User;

/**
 * The way back for a shopper with no password: the links in the e-mails we send to a guest carry a signed pass to their own session. It works for
 * a month, only while they are still a guest, and only while the e-mail address it was sent to is still the one on record. The pass travels after
 * the "#" of the address, which browsers never send to a server, so it does not end up in access logs or referrers.
 */
class GuestAccessLink
{
    public const DAYS = 30;

    /** The address to put in an e-mail for this person: a guest gets the signed way in, everyone else the plain page. */
    public static function to(object $notifiable, string $path): string
    {
        $web = rtrim((string) config('platform.web_url'), '/');
        if (! $notifiable instanceof User || ! $notifiable->is_guest) {
            return $web.$path;
        }

        return $web.'/guest-access#'.http_build_query(['t' => self::token($notifiable, now()->addDays(self::DAYS)->getTimestamp()), 'to' => $path]);
    }

    /** The guest this pass belongs to, or null when it is forged, old or no longer applies. */
    public static function guest(string $token): ?User
    {
        if (! preg_match('/^(\d{1,18})\.(\d{1,12})\.[0-9a-f]{64}$/', $token, $m) || (int) $m[2] < now()->getTimestamp()) {
            return null;
        }
        $user = User::find((int) $m[1]);

        return $user && $user->is_guest && $user->is_active && $user->contact_email && hash_equals(self::token($user, (int) $m[2]), $token) ? $user : null;
    }

    private static function token(User $user, int $expires): string
    {
        return "{$user->id}.{$expires}.".hash_hmac('sha256', "guest-access|{$user->id}|{$expires}|{$user->contact_email}", (string) config('app.key'));
    }
}
