<?php

namespace App\Services;

use App\Models\Partner;
use App\Models\User;
use App\Notifications\InstallationUpdate;

class InstallationNotifier
{
    public function user(?int $userId, string $title, string $body, string $path): void
    {
        if ($userId && ($user = User::withoutGlobalScopes()->find($userId))) {
            $user->notify(new InstallationUpdate($title, $body, $path));
        }
    }

    public function centre(int $partnerId, string $title, string $body, string $path): void
    {
        User::withoutGlobalScopes()->where('partner_id', $partnerId)->role('partner-manager')->get()
            ->each(fn (User $u) => $u->notify(new InstallationUpdate($title, $body, $path)));
    }

    public function admins(string $title, string $body, string $path): void
    {
        User::withoutGlobalScopes()->role('platform-admin')->get()->each(fn (User $u) => $u->notify(new InstallationUpdate($title, $body, $path)));
    }
}
