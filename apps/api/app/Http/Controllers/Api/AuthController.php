<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\StrongPassword;
use App\Support\Audit;
use App\Support\Security\AuthCookie;
use App\Support\Security\BotGuard;
use App\Support\Security\EmailPolicy;
use App\Support\Settings\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /** Same wording for every bot-ish failure, so a script learns nothing about which check caught it. */
    private const BOT_MESSAGE = 'We could not create your account. Please reload the page and try again.';

    /** The timing token + captcha settings the signup/forgot-password forms need. */
    public function challenge(Request $request, BotGuard $bots)
    {
        return $bots->challenge($request);
    }

    /** Public signup creates a customer of the chosen company (storefront). */
    public function register(Request $request, BotGuard $bots, EmailPolicy $emails, Settings $settings)
    {
        if (! $settings->bool('platform.registration_open')) {
            return response()->json(['message' => 'Signups are temporarily closed. Please try again later.'], 403);
        }

        if (! $bots->passes($request)) {
            Audit::record('auth.signup_blocked', null, ['reason' => 'bot_check', 'email' => $request->input('email')]);

            return response()->json(['message' => self::BOT_MESSAGE], 422);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => StrongPassword::rules(confirmed: true),
            // Either the store the visitor came from (its public address) or a company id.
            'store' => 'nullable|string|max:60',
            'company_id' => 'required_without:store|nullable|integer|exists:companies,id',
            'phone' => 'nullable|string|max:30',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        if (! empty($data['store'])) {
            $store = app(\App\Services\Storefront\StorefrontService::class)->find($data['store']);

            if (! $store) {
                throw ValidationException::withMessages(['store' => ['This store was not found.']]);
            }

            $data['company_id'] = $store->id;
        }

        if ($emails->isBlocked($data['email'])) {
            Audit::record('auth.signup_blocked', null, ['reason' => 'email_domain', 'email' => $data['email']]);

            throw ValidationException::withMessages(['email' => ['Please use a permanent email address.']]);
        }

        // a+1@x.com and a+2@x.com are one mailbox, not two customers.
        if (User::where('email_canonical', EmailPolicy::canonical($data['email']))->exists()) {
            throw ValidationException::withMessages(['email' => ['The email has already been taken.']]);
        }

        // A phone number is checked against the store's country (and stored as +966…/+20…).
        $phone = null;
        if (! empty($data['phone'])) {
            $company = \App\Models\Company::withoutGlobalScopes()->with('tenant')->find($data['company_id']);
            $market = app(\App\Support\Markets\Markets::class)->forCompany($company);
            $phone = app(\App\Support\Markets\Markets::class)->phoneFor($company, $data['phone']);

            if (! $phone) {
                throw ValidationException::withMessages(['phone' => ['Please enter a valid '.($market?->name() ?? '').' mobile number.']]);
            }
        }

        $hasLocation = isset($data['latitude'], $data['longitude']);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $phone,
            'password' => $data['password'],
            'company_id' => $data['company_id'],
            'city' => $data['city'] ?? null,
            'country' => $data['country'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'location_enabled' => $hasLocation,
        ]);

        $user->assignRole('customer');
        Audit::record('auth.registered', $user, [], $user);

        if ($emails->verificationRequired()) {
            $user->sendEmailVerificationNotification();
        }

        return $this->signedIn($request, $user, 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email|max:255',
            'password' => 'required|string|max:255',
        ]);

        $user = User::where('email', mb_strtolower(trim($data['email'])))->first();

        // Always do the (slow) password check, even for unknown emails, so response time
        // cannot be used to discover which emails have accounts.
        $valid = Hash::check($data['password'], $user?->password ?? $this->dummyHash()) && $user !== null;

        if (! $valid) {
            Audit::record('auth.login_failed', null, ['email' => mb_strtolower($data['email'])]);

            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        if (! $user->is_active) {
            Audit::record('auth.login_blocked', $user, ['reason' => 'deactivated'], $user);

            throw ValidationException::withMessages([
                'email' => ['This account has been deactivated.'],
            ]);
        }

        Audit::record('auth.login', $user, [], $user);

        return $this->signedIn($request, $user);
    }

    /** Issues the session: an HttpOnly cookie for browsers, a Bearer token for everything else. */
    private function signedIn(Request $request, User $user, int $status = 200)
    {
        $token = $user->createToken('auth-token')->plainTextToken;

        // Cap how many devices stay signed in; the oldest sessions are dropped first.
        $keep = max(1, (int) app(Settings::class)->get('security.max_active_tokens'));
        $stale = $user->tokens()->orderByDesc('id')->skip($keep)->take(1000)->pluck('id');
        if ($stale->isNotEmpty()) {
            $user->tokens()->whereIn('id', $stale)->delete();
        }

        if (AuthCookie::wanted($request)) {
            return response()->json(['user' => $user], $status)->withCookie(AuthCookie::make($request, $token));
        }

        return response()->json(['user' => $user, 'token' => $token], $status);
    }

    /** A hash with the same cost as real ones, computed once. */
    private function dummyHash(): string
    {
        static $hash = null;

        return $hash ??= Hash::make(bin2hex(random_bytes(16)));
    }

    public function me(Request $request)
    {
        $user = $request->user()->load('company.tenant.industry');
        $industry = $user->company?->tenant?->industry;

        $emails = app(EmailPolicy::class);

        $market = app(\App\Support\Markets\Markets::class)->forCompany($user->company);

        return response()->json(array_merge($user->toArray(), [
            'market' => $market?->toArray(),
            'email_verified' => $user->hasVerifiedEmail(),
            'email_verification_required' => $emails->verificationRequired() && $user->hasRole('customer') && ! $user->hasVerifiedEmail(),
            'industry' => $industry ? [
                'id' => $industry->id,
                'key' => $industry->key,
                'name' => $industry->name,
                'subjects' => array_keys($industry->module()?->subjectModels() ?? []),
                'targets' => array_keys($industry->module()?->targetModels() ?? []),
                'catalogs' => $industry->module() ? app(\App\Modules\IndustryRegistry::class)->manageableAliases($industry->module()) : [],
            ] : null,
            'roles' => $user->getRoleNames(),
        ]));
    }

    /** Lets a customer share where they are so nearby partners can be found. */
    public function updateLocation(Request $request)
    {
        $data = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
        ]);

        $request->user()->update($data + ['location_enabled' => true]);

        return $this->me($request);
    }

    public function logout(Request $request)
    {
        $token = $request->user()->currentAccessToken();

        if (method_exists($token, 'delete')) {
            $token->delete();
        }

        Audit::record('auth.logout');

        return response()->json(['message' => 'Logged out successfully'])->withCookie(AuthCookie::forget($request));
    }

    public function destroy(\App\Models\Order $order)
    {
    abort_unless(
        auth()->user()->hasRole('platform-admin'),
        403
    );

    $order->delete();

    return response()->json([
        'message' => 'Deleted'
    ]);
}
}