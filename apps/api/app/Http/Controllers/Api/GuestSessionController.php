<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Storefront\StorefrontService;
use App\Support\Audit;
use App\Support\Security\EmailPolicy;
use App\Rules\StrongPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Shopping without signing up. The first time someone adds something to a cart, a quiet shopper record is made for them and their browser is
 * signed in as it: the cart, checkout, payment, tracking, returns and messages are then the very same ones every customer uses. They give a real
 * e-mail at checkout (to write to), and can claim a full account whenever they like.
 */
class GuestSessionController extends Controller
{
    /** Starts a session for this store, or tells who is already signed in. */
    public function start(Request $request, StorefrontService $stores, AuthController $auth)
    {
        $data = $request->validate(['store' => 'required|string|max:80']);
        $company = $stores->find($data['store']) ?? throw ValidationException::withMessages(['store' => ['This store was not found.']]);

        if ($request->user('sanctum')) {
            return response()->json(['user' => $request->user('sanctum')]);
        }

        $user = User::create([
            'name' => 'Guest', 'email' => 'guest-'.Str::uuid().'@guest.invalid', 'password' => Str::random(40), 'company_id' => $company->id, 'email_verified_at' => now(),
        ]);
        $user->forceFill(['is_guest' => true])->save();
        $user->assignRole('customer');

        return $auth->signedIn($request, $user, 201);
    }

    /** Where we write to them about this purchase (only for a guest). */
    public function contact(Request $request)
    {
        $user = $this->guest($request);
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:255'], 'name' => 'nullable|string|max:120']);
        if (app(EmailPolicy::class)->isBlocked($data['email'])) {
            throw ValidationException::withMessages(['email' => ['Please use a permanent email address.']]);
        }
        $user->forceFill(['contact_email' => mb_strtolower(trim($data['email'])), 'name' => trim((string) ($data['name'] ?? '')) ?: $user->name])->save();

        return ['contact_email' => $user->contact_email];
    }

    /** Turns the guest into a full account with the e-mail and password they choose; everything they bought stays theirs. */
    public function claim(Request $request)
    {
        $user = $this->guest($request);
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'], 'password' => StrongPassword::rules(confirmed: true)]);
        if (User::where('email_canonical', EmailPolicy::canonical($data['email']))->exists()) {
            throw ValidationException::withMessages(['email' => ['The email has already been taken.']]);
        }
        $user->forceFill(['email' => mb_strtolower(trim($data['email'])), 'password' => $data['password'], 'is_guest' => false, 'contact_email' => null, 'email_verified_at' => null])->save();
        Audit::record('auth.guest_claimed', $user, [], $user);
        if (app(EmailPolicy::class)->verificationRequired()) {
            $user->sendEmailVerificationNotification();
        }

        return ['user' => $user->fresh()];
    }

    private function guest(Request $request): User
    {
        $user = $request->user();
        abort_unless($user && $user->is_guest, 403);

        return $user;
    }
}
