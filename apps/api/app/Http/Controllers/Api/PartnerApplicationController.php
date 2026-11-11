<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PartnerApplication;
use App\Models\User;
use App\Notifications\PartnerApplicationReceived;
use App\Rules\StrongPassword;
use App\Services\PartnerApplicationService;
use App\Services\Storefront\StorefrontService;
use App\Support\Audit;
use App\Support\Markets\Markets;
use App\Support\Security\BotGuard;
use App\Support\Security\EmailPolicy;
use App\Support\Settings\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PartnerApplicationController extends Controller
{
    private const BOT_MESSAGE = 'We could not send your application. Please reload the page and try again.';

    /** Public: a business applies to join a store as a service partner. Nothing is granted until a reviewer approves. */
    public function apply(Request $request, BotGuard $bots, EmailPolicy $emails, Settings $settings, StorefrontService $stores, Markets $markets)
    {
        if (! $settings->bool('platform.partner_signup_open')) {
            return response()->json(['message' => 'Partner applications are closed right now.'], 403);
        }

        if (! $bots->passes($request)) {
            Audit::record('partner_application.blocked', null, ['reason' => 'bot_check', 'email' => $request->input('email')]);

            return response()->json(['message' => self::BOT_MESSAGE], 422);
        }

        $data = $request->validate([
            'store' => 'required|string|max:60',
            'business_name' => 'required|string|max:150',
            'contact_name' => 'required|string|max:120',
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => 'required|string|max:30',
            'city' => 'required|string|max:100',
            'country' => 'required|string|size:2|alpha',
            'description' => 'nullable|string|max:400',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'password' => StrongPassword::rules(confirmed: true),
        ]);

        $store = $stores->find($data['store']) ?? throw ValidationException::withMessages(['store' => ['This store was not found.']]);

        if ($emails->isBlocked($data['email'])) {
            Audit::record('partner_application.blocked', null, ['reason' => 'email_domain', 'email' => $data['email']]);
            throw ValidationException::withMessages(['email' => ['Please use a permanent email address.']]);
        }

        $canonical = EmailPolicy::canonical($data['email']);
        if (User::where('email_canonical', $canonical)->exists()
            || PartnerApplication::where('email_canonical', $canonical)->whereIn('status', [PartnerApplication::PENDING, PartnerApplication::APPROVED])->exists()) {
            throw ValidationException::withMessages(['email' => ['The email has already been taken.']]);
        }

        $phone = $markets->phoneFor($store, $data['phone']) ?? throw ValidationException::withMessages(['phone' => ['Please enter a valid mobile number.']]);

        $application = PartnerApplication::create([
            'company_id' => $store->id, 'tenant_id' => $store->tenant_id, 'business_name' => $data['business_name'], 'contact_name' => $data['contact_name'],
            'email' => mb_strtolower(trim($data['email'])), 'email_canonical' => $canonical, 'phone' => $phone, 'city' => $data['city'],
            'country' => strtoupper($data['country']), 'description' => $data['description'] ?? null, 'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null, 'password' => Hash::make($data['password']), 'status' => PartnerApplication::PENDING,
        ]);
        Audit::record('partner_application.submitted', $application, ['store' => $store->slug]);

        // Tell the people who can approve: platform admins and this store's owners.
        $reviewers = User::role('platform-admin')->get()->merge(User::role('company-owner')->where('company_id', $store->id)->get())->unique('id');
        foreach ($reviewers->where('is_active', true) as $reviewer) {
            try {
                $reviewer->notify(new PartnerApplicationReceived($application->business_name, $store->name));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json(['message' => 'Application received. You can sign in as soon as it is approved.'], 201);
    }

    /** Reviewers see the applications they may decide on: everything for admins, their own store's for owners. */
    public function index(Request $request)
    {
        $request->validate(['status' => 'nullable|in:pending,approved,rejected,all']);
        $user = $this->reviewer($request);
        $status = $request->query('status', 'pending');

        return PartnerApplication::with('store:id,name,slug')
            ->when(! $user->hasRole('platform-admin'), fn ($q) => $q->where('company_id', $user->company_id))
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest('id')->paginate(20);
    }

    public function approve(Request $request, int $id, PartnerApplicationService $service)
    {
        $app = $this->decidable($request, $id);

        return $service->approve($app, $request->user());
    }

    public function reject(Request $request, int $id, PartnerApplicationService $service)
    {
        $app = $this->decidable($request, $id);
        $data = $request->validate(['reason' => 'nullable|string|max:300']);

        return $service->reject($app, $request->user(), $data['reason'] ?? null);
    }

    private function reviewer(Request $request): User
    {
        $user = $request->user();
        abort_unless($user->hasRole('platform-admin') || ($user->hasRole('company-owner') && $user->company_id), 403);

        return $user;
    }

    private function decidable(Request $request, int $id): PartnerApplication
    {
        $user = $this->reviewer($request);
        $app = PartnerApplication::findOrFail($id);

        // An owner can only decide on applications to their own store; to anyone else it does not exist.
        abort_unless($user->hasRole('platform-admin') || $app->company_id === $user->company_id, 404);

        return $app;
    }
}
