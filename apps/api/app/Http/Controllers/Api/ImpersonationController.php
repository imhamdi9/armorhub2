<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ImpersonationReadOnly;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;

/**
 * "View as": a platform administrator sees exactly what another person sees, to help them. The session is read-only, lasts 30 minutes,
 * is written to the audit log (who looked at whom), and leaving it issues the administrator a fresh sign-in on the server.
 */
class ImpersonationController extends Controller
{
    private const MINUTES = 30;

    public function start(Request $request, int $id, AuthController $auth)
    {
        $admin = $request->user();
        abort_unless($admin->hasRole('platform-admin'), 403);
        abort_if(ImpersonationReadOnly::viewedBy($admin) !== null, 422, 'Leave the account you are viewing first.');

        $target = User::findOrFail($id);
        abort_if($target->is($admin), 422, 'You are already signed in as yourself.');
        abort_if($target->hasRole('platform-admin'), 422, 'You cannot view as another platform administrator.');
        abort_unless($target->is_active, 422, 'This account is deactivated.');

        $token = $target->createToken(ImpersonationReadOnly::TOKEN_PREFIX.$admin->id, ['*'], now()->addMinutes(self::MINUTES))->plainTextToken;
        Audit::record('admin.impersonation_started', $target, ['viewed_user' => $target->email, 'minutes' => self::MINUTES], $admin);

        return $this->issue($request, $target, $token);
    }

    public function exit(Request $request, AuthController $auth)
    {
        $viewed = $request->user();
        $adminId = ImpersonationReadOnly::viewedBy($viewed);
        abort_if($adminId === null, 422, 'You are not viewing as anyone.');

        $admin = User::findOrFail($adminId);
        $viewed->currentAccessToken()->delete();
        Audit::record('admin.impersonation_ended', $viewed, ['viewed_user' => $viewed->email], $admin);

        return $auth->signedIn($request, $admin);
    }

    private function issue(Request $request, User $user, string $token)
    {
        if (\App\Support\Security\AuthCookie::wanted($request)) {
            return response()->json(['user' => $user])->withCookie(\App\Support\Security\AuthCookie::make($request, $token));
        }

        return response()->json(['user' => $user, 'token' => $token]);
    }
}
