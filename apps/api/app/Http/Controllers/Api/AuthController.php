<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

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