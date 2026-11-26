<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\MediaFile;
use App\Support\Audit;
use App\Support\ContactGuard;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** What a seller tells shoppers about the shop (public) and where returned goods go (private). */
class SellerProfileController extends Controller
{
    private function seller(Request $request): Company
    {
        $user = $request->user();
        abort_unless($user->hasRole('seller') && $user->company_id, 403);

        return Company::withoutGlobalScopes()->findOrFail($user->company_id);
    }

    public function show(Request $request)
    {
        $c = $this->seller($request);

        return ['name' => $c->name, 'profile' => $this->clean($c->settings['profile'] ?? [])];
    }

    public function update(Request $request)
    {
        $c = $this->seller($request);
        $d = $request->validate([
            'about' => 'nullable|string|max:600', 'city' => 'nullable|string|max:80', 'hours' => 'nullable|string|max:120', 'ships_within_days' => 'nullable|integer|between:1,14',
            'logo' => ['nullable', 'string', 'regex:#^/media/[0-9a-f-]{36}\.(jpg|png|webp|gif)$#'],
            'return_address' => 'nullable|array', 'return_address.line1' => 'nullable|string|max:200', 'return_address.city' => 'nullable|string|max:80', 'return_address.notes' => 'nullable|string|max:200',
        ]);

        // What shoppers read must not carry a way around the platform.
        foreach (['about', 'hours'] as $field) {
            if (! empty($d[$field]) && ContactGuard::contains($d[$field])) {
                throw ValidationException::withMessages([$field => ['Please do not put phone numbers, e-mails or links here. Shoppers reach you through the platform.']]);
            }
        }
        if (! empty($d['logo']) && ! MediaFile::where('name', substr($d['logo'], 7))->where('uploaded_by', $request->user()->id)->exists()) {
            throw ValidationException::withMessages(['logo' => ['Only a picture you uploaded can be your logo.']]);
        }

        $settings = $c->settings ?? [];
        $settings['profile'] = $this->clean($d);
        $c->forceFill(['settings' => $settings])->save();
        Audit::record('seller.profile_saved', $c, [], $request->user());

        return ['name' => $c->name, 'profile' => $settings['profile']];
    }

    /** @param array<string, mixed> $in @return array<string, mixed> */
    private function clean(array $in): array
    {
        $text = fn ($v) => ($v = trim(strip_tags((string) $v))) === '' ? null : $v;

        return [
            'about' => $text($in['about'] ?? null), 'city' => $text($in['city'] ?? null), 'hours' => $text($in['hours'] ?? null), 'logo' => $in['logo'] ?? null,
            'ships_within_days' => isset($in['ships_within_days']) ? (int) $in['ships_within_days'] : null,
            'return_address' => ['line1' => $text($in['return_address']['line1'] ?? null), 'city' => $text($in['return_address']['city'] ?? null), 'notes' => $text($in['return_address']['notes'] ?? null)],
        ];
    }
}
