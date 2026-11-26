<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    /** Always answers the same way, so it cannot be used to discover which emails exist. */
    public function forgot(Request $request, \App\Support\Security\BotGuard $bots)
    {
        $request->validate(['email' => 'required|email|max:255']);

        // Scripts abusing this to spam a victim's inbox get the same answer but no email.
        if ($bots->passes($request)) {
            Password::sendResetLink($request->only('email'));
        } else {
            \App\Support\Audit::record('auth.reset_blocked', null, ['email' => $request->input('email')]);
        }

        return response()->json(['message' => 'If that email has an account, a reset link has been sent.']);
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => \App\Rules\StrongPassword::rules(confirmed: true),
        ]);

        $status = Password::reset($data, function (User $user, string $password) {
            $user->forceFill(['password' => $password])->save();
            $user->tokens()->delete(); // old sessions must not survive a reset
            \App\Support\Audit::record('auth.password_reset', $user, [], $user);
            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }

        return response()->json(['message' => 'Password updated. You can sign in now.']);
    }
}
