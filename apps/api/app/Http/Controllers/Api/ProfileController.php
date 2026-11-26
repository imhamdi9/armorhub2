<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/** The signed-in person's own details: name, phone and password. */
class ProfileController extends Controller
{
    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate(['name' => 'sometimes|required|string|max:120', 'phone' => 'sometimes|nullable|string|max:30']);

        if (array_key_exists('phone', $data)) {
            if ($data['phone'] === null || $data['phone'] === '') {
                $data['phone'] = null;
            } else {
                // The number is checked against the country of the person's store and stored in international form.
                $phone = app(\App\Support\Markets\Markets::class)->phoneFor($user->company, $data['phone']);
                if (! $phone) {
                    throw ValidationException::withMessages(['phone' => ['Please enter a valid mobile number.']]);
                }
                $data['phone'] = $phone;
            }
        }
        if (isset($data['name'])) {
            $data['name'] = trim(strip_tags($data['name']));
        }
        $user->update($data);
        Audit::record('auth.profile_updated', $user, array_keys($data), $user);

        return ['name' => $user->name, 'phone' => $user->phone];
    }

    public function preferences(Request $request)
    {
        $u = $request->user();

        return ['notify_email' => (bool) $u->notify_email, 'notify_push' => (bool) $u->notify_push];
    }

    public function savePreferences(Request $request)
    {
        $data = $request->validate(['notify_email' => 'sometimes|boolean', 'notify_push' => 'sometimes|boolean']);
        $u = $request->user();
        $u->forceFill($data)->save();

        return ['notify_email' => (bool) $u->notify_email, 'notify_push' => (bool) $u->notify_push];
    }

    public function password(Request $request)
    {
        $user = $request->user();
        $data = $request->validate(['current_password' => 'required|string|max:255', 'password' => ['required', 'string', 'confirmed', Password::min(8)->letters()->numbers()]]);

        if (! Hash::check($data['current_password'], $user->password)) {
            $social = SocialAccount::where('user_id', $user->id)->exists();
            throw ValidationException::withMessages(['current_password' => [$social
                ? 'Your account signs in with Google, Facebook or Apple. Use "Forgot your password?" to set a password.'
                : 'The current password is not correct.']]);
        }

        $user->forceFill(['password' => $data['password']])->save();
        // Every other device is signed out; this one stays.
        $current = $user->currentAccessToken();
        $user->tokens()->when($current?->id, fn ($q, $id) => $q->where('id', '!=', $id))->delete();
        Audit::record('auth.password_changed', $user, [], $user);

        return ['message' => 'Password changed.'];
    }
}
