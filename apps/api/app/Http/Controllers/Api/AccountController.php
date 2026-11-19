<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Models\Order;
use App\Models\SocialAccount;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** A customer can delete their own account from the app (the stores require it). Orders are financial records, so they stay, without the person. */
class AccountController extends Controller
{
    /** Everything held about the signed-in customer, as a file they can keep. Staff and business accounts ask support: their data includes other people's. */
    public function export(Request $request, \App\Services\DataExport $export)
    {
        $user = $request->user();
        if ($user->getRoleNames()->diff(['customer'])->isNotEmpty() || $user->partner_id !== null) {
            throw ValidationException::withMessages(['account' => ['Business and staff accounts ask support for their data.']]);
        }
        Audit::record('account.exported', $user, [], $user);

        return response()->json($export->of($user), 200, [
            'Content-Disposition' => 'attachment; filename="armorhub-my-data.json"', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'no-store',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function destroy(Request $request)
    {
        $user = $request->user();

        // Businesses and staff have money, products and teammates attached: those accounts are closed with support, not with a button.
        if ($user->getRoleNames()->diff(['customer'])->isNotEmpty() || $user->partner_id !== null) {
            throw ValidationException::withMessages(['account' => ['Business and staff accounts are closed through support. Please contact us.']]);
        }

        $data = $request->validate(['password' => 'nullable|string|max:255', 'confirm' => 'nullable|string|max:20']);

        // Someone who signed up with Google, Facebook or Apple has no password they know: they confirm by typing DELETE instead.
        $social = SocialAccount::where('user_id', $user->id)->exists();
        $proved = $social ? ($data['confirm'] ?? '') === 'DELETE' : Hash::check($data['password'] ?? '', $user->password);
        if (! $proved) {
            throw ValidationException::withMessages([$social ? 'confirm' : 'password' => [$social ? 'Type DELETE to confirm.' : 'The password is not correct.']]);
        }

        $open = Order::withoutGlobalScopes()->where('customer_id', $user->id)->whereNotIn('status', [OrderStatus::Completed->value, OrderStatus::Cancelled->value])->exists();
        if ($open) {
            throw ValidationException::withMessages(['account' => ['You have an order in progress. Finish or cancel it first.']]);
        }

        DB::transaction(function () use ($user) {
            Audit::record('account.deleted', $user, [], $user);

            $user->tokens()->delete();
            DeviceToken::where('user_id', $user->id)->delete();
            SocialAccount::where('user_id', $user->id)->delete();
            $user->notifications()->delete();

            // The row stays (orders point to it) but nothing about the person does.
            $user->forceFill([
                'name' => 'Deleted user', 'email' => "deleted+{$user->id}@deleted.invalid", 'email_canonical' => "deleted+{$user->id}@deleted.invalid",
                'phone' => null, 'city' => null, 'latitude' => null, 'longitude' => null, 'location_enabled' => false,
                'password' => Hash::make(bin2hex(random_bytes(24))), 'is_active' => false, 'email_verified_at' => null, 'remember_token' => null,
            ])->save();
        });

        return response()->json(['message' => 'Your account was deleted.']);
    }
}
