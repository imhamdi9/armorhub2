<?php

namespace App\Services\Push;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * Sends a push notification to every phone a person is signed in on, through Expo's push service (which delivers to Apple and Google).
 * It never blocks or breaks what the person was doing: a failure is reported and forgotten.
 */
class PushSender
{
    private const ENDPOINT = 'https://exp.host/--/api/v2/push/send';

    public function enabled(): bool
    {
        return (bool) config('services.expo_push.enabled');
    }

    /** @param array<string, mixed> $data what the app needs to open the right screen (a path) */
    public function toUser(User $user, string $title, string $body, array $data = []): void
    {
        if (! $this->enabled()) {
            return;
        }

        $tokens = DeviceToken::where('user_id', $user->id)->pluck('token')->all();
        if ($tokens === []) {
            return;
        }

        try {
            $request = Http::timeout(5)->acceptJson();
            if ($key = config('services.expo_push.access_token')) {
                $request = $request->withToken($key);
            }

            $messages = array_map(fn ($t) => ['to' => $t, 'title' => mb_substr($title, 0, 120), 'body' => mb_substr($body, 0, 240), 'data' => $data, 'sound' => 'default'], $tokens);
            $tickets = $request->post(self::ENDPOINT, $messages)->json('data') ?? [];

            // A phone that signed out or uninstalled the app reports it: forget its token so it is not tried again.
            foreach ($tickets as $i => $ticket) {
                if (($ticket['status'] ?? '') === 'error' && ($ticket['details']['error'] ?? '') === 'DeviceNotRegistered') {
                    DeviceToken::where('token', $tokens[$i] ?? '')->delete();
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
