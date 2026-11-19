<?php

namespace App\Services\Marketing;

use App\Models\User;
use App\Notifications\SimpleNotice;
use Illuminate\Support\Facades\Cache;

/** Tells the platform admins when an ad platform stops receiving data or reading fails, or a day's spend passes the limit. At most once a day per cause. */
class AdAlerts
{
    public static function send(string $cause, string $title, string $body, array $replace = []): void
    {
        if (! Cache::add('ad-alert:'.$cause.':'.now()->toDateString(), 1, 86400)) {
            return;
        }
        User::withoutGlobalScopes()->role('platform-admin')->where('is_active', true)->get()
            ->each(fn (User $u) => $u->notify(new SimpleNotice($title, $body, '/dashboard/admin/marketing', $replace)));
    }
}
