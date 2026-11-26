<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Auth\SocialIdentity;
use App\Services\Storefront\StorefrontService;
use App\Support\Audit;
use App\Support\Security\EmailPolicy;
use App\Support\Settings\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** "Continue with Google / Facebook / Apple": signs in, links to an existing account with the same confirmed email, or creates a customer. */
class SocialAuthController extends Controller
{
    /** Which buttons the sign-in and sign-up pages show (public). */
    public function providers(SocialIdentity $social)
    {
        return response()->json(['providers' => $social->enabled()]);
    }

    public function login(Request $request, SocialIdentity $social, EmailPolicy $emails, Settings $settings)
    {
        $data = $request->validate([
            'provider' => 'required|string|in:'.implode(',', SocialIdentity::PROVIDERS),
            'credential' => 'required|string|max:4000',
            // Only the sign-up page sends the store a new customer joins.
            'store' => 'nullable|string|max:60',
            // Apple shares the name only the first time, and only to the browser.
            'name' => 'nullable|string|max:255',
        ]);

        $identity = $social->verify($data['provider'], $data['credential']);

        $link = SocialAccount::where('provider', $data['provider'])->where('provider_user_id', $identity['id'])->first();
        $user = $link?->user;

        if (! $user && $identity['email'] && $identity['email_verified']) {
            // The provider vouches for this mailbox, so it is the same person as the account with that email.
            $user = User::where('email_canonical', EmailPolicy::canonical($identity['email']))->first();
        }

        if (! $user) {
            if (empty($data['store'])) {
                throw ValidationException::withMessages(['credential' => ['There is no account for this sign-in yet. Create one first.']]);
            }
            if (! $identity['email'] || ! $identity['email_verified']) {
                throw ValidationException::withMessages(['credential' => ['Your account did not share a confirmed email address. Please sign up with email instead.']]);
            }
            if (! $settings->bool('platform.registration_open')) {
                throw ValidationException::withMessages(['credential' => ['Signups are temporarily closed. Please try again later.']]);
            }
            if ($emails->isBlocked($identity['email'])) {
                throw ValidationException::withMessages(['credential' => ['Please use a permanent email address.']]);
            }

            $store = app(StorefrontService::class)->find($data['store']) ?? throw ValidationException::withMessages(['store' => ['This store was not found.']]);

            $user = User::create([
                'name' => $identity['name'] ?: ($data['name'] ?? null) ?: strstr($identity['email'], '@', true),
                'email' => mb_strtolower($identity['email']),
                // No password to remember: signing in is always through the provider (a reset link can set one later).
                'password' => Hash::make(bin2hex(random_bytes(24))),
                'company_id' => $store->id,
                'email_verified_at' => now(),
                'is_active' => true,
            ]);
            $user->assignRole('customer');
            Audit::record('auth.registered', $user, ['via' => $data['provider']], $user);
        }

        if (! $user->is_active) {
            Audit::record('auth.login_blocked', $user, ['reason' => 'deactivated'], $user);
            throw ValidationException::withMessages(['credential' => ['This account has been deactivated.']]);
        }

        if (! $link) {
            SocialAccount::firstOrCreate(['provider' => $data['provider'], 'provider_user_id' => $identity['id']], ['user_id' => $user->id]);
        }
        if ($user->email_verified_at === null && $identity['email_verified']) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        Audit::record('auth.login', $user, ['via' => $data['provider']], $user);

        return app(AuthController::class)->signedIn($request, $user);
    }
}
