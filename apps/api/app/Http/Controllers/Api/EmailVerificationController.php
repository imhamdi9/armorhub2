<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    /** Opened from the emailed (signed) link, then sent back to the web app. */
    public function verify(Request $request, int $id, string $hash)
    {
        $user = User::findOrFail($id);
        $web = rtrim(config('platform.web_url'), '/');

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return redirect("{$web}/login?verified=0");
        }

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
            \App\Models\Order::claimGuestOrders($user->fresh());
            Audit::record('auth.email_verified', $user, [], $user);
        }

        return redirect("{$web}/login?verified=1");
    }

    public function resend(Request $request)
    {
        $user = $request->user();

        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json(['message' => 'If your email is not verified yet, a new link is on its way.']);
    }
}
