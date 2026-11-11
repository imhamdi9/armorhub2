<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /** Public signup creates a customer of the chosen company (storefront). */
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8|confirmed',
            'company_id' => 'required|integer|exists:companies,id',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        $hasLocation = isset($data['latitude'], $data['longitude']);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'company_id' => $data['company_id'],
            'city' => $data['city'] ?? null,
            'country' => $data['country'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'location_enabled' => $hasLocation,
        ]);

        $user->assignRole('customer');

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['This account has been deactivated.'],
            ]);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user()->load('company.tenant.industry');
        $industry = $user->company?->tenant?->industry;

        return response()->json(array_merge($user->toArray(), [
            'industry' => $industry ? [
                'id' => $industry->id,
                'key' => $industry->key,
                'name' => $industry->name,
                'subjects' => array_keys($industry->module()?->subjectModels() ?? []),
                'targets' => array_keys($industry->module()?->targetModels() ?? []),
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
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
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