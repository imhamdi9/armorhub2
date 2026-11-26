<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\Request;

/** The phone tells the platform where to send push notifications for the person signed in on it. */
class DeviceController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'platform' => 'required|in:ios,android',
            // An Expo push token looks like ExponentPushToken[xxxx]; nothing else is accepted.
            'token' => ['required', 'string', 'max:255', 'regex:/^Expo(nent)?PushToken\[[A-Za-z0-9_-]+\]$/'],
        ]);

        // A phone belongs to whoever signed in last: the token moves to them instead of keeping the previous owner's alerts coming.
        DeviceToken::updateOrCreate(['token' => $data['token']], ['user_id' => $request->user()->id, 'platform' => $data['platform'], 'last_seen_at' => now()]);

        return response()->json(['registered' => true], 201);
    }

    public function destroy(Request $request)
    {
        $data = $request->validate(['token' => 'required|string|max:255']);
        DeviceToken::where('user_id', $request->user()->id)->where('token', $data['token'])->delete();

        return response()->json(['message' => 'Removed']);
    }
}
