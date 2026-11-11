<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\Audit;
use App\Support\SecurityChecks;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class SecurityController extends Controller
{
    /** Configuration health plus the last day's suspicious activity. */
    public function overview(SecurityChecks $checks)
    {
        $since = now()->subDay();
        $count = fn (string $action) => AuditLog::where('action', $action)->where('created_at', '>=', $since)->count();

        return [
            'checks' => $checks->run(),
            'last_24h' => [
                'failed_logins' => $count('auth.login_failed'),
                'blocked_signups' => $count('auth.signup_blocked'),
                'blocked_resets' => $count('auth.reset_blocked'),
                'new_accounts' => $count('auth.registered'),
            ],
            // Addresses with the most failed sign-ins, to spot an attacker at a glance.
            'top_offenders' => AuditLog::where('action', 'auth.login_failed')->where('created_at', '>=', $since)
                ->selectRaw('ip, count(*) as attempts')->groupBy('ip')->orderByDesc('attempts')->limit(5)->get(),
            'active_sessions' => PersonalAccessToken::count(),
        ];
    }

    /** Emergency button: everybody (except the person pressing it) has to sign in again. */
    public function revokeAllSessions(Request $request)
    {
        $keep = $request->user()->currentAccessToken();
        $query = PersonalAccessToken::query();

        if ($keep instanceof PersonalAccessToken) {
            $query->where('id', '!=', $keep->id);
        }

        $count = $query->delete();
        Audit::record('security.all_sessions_revoked', null, ['sessions' => $count]);

        return response()->json(['message' => "Signed out {$count} session(s)."]);
    }
}
