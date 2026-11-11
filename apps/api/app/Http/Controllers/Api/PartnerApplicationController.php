<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApplicationDocument;
use App\Models\PartnerApplication;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\PartnerApplicationReceived;
use App\Rules\StrongPassword;
use App\Services\ApplicationDocuments;
use App\Services\PartnerApplicationService;
use App\Services\Storefront\StorefrontService;
use App\Support\Audit;
use App\Support\Markets\Markets;
use App\Support\Security\BotGuard;
use App\Support\Security\EmailPolicy;
use App\Support\Settings\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Businesses apply to join: as a service partner (to a store) or as a seller (to a marketplace). They upload verification
 * documents; a reviewer checks them and decides. Nothing is granted until then.
 */
class PartnerApplicationController extends Controller
{
    private const BOT_MESSAGE = 'We could not send your application. Please reload the page and try again.';

    public function apply(Request $request, BotGuard $bots, EmailPolicy $emails, Settings $settings, StorefrontService $stores, Markets $markets, ApplicationDocuments $documents)
    {
        $type = $request->input('type') === 'seller' ? 'seller' : 'partner';

        if (! $settings->bool($type === 'seller' ? 'platform.seller_signup_open' : 'platform.partner_signup_open')) {
            return response()->json(['message' => 'Applications are closed right now.'], 403);
        }

        if (! $bots->passes($request)) {
            Audit::record('partner_application.blocked', null, ['reason' => 'bot_check', 'email' => $request->input('email')]);

            return response()->json(['message' => self::BOT_MESSAGE], 422);
        }

        $maxKb = (int) config('platform.document_max_kb');
        $file = ['file', 'mimes:pdf,jpg,jpeg,png', "max:{$maxKb}"];
        $required = ApplicationDocuments::required($type);
        $doc = fn (string $kind) => in_array($kind, $required, true) ? array_merge(['required'], $file) : array_merge(['nullable'], $file);

        $data = $request->validate([
            'type' => 'required|in:partner,seller',
            'store' => $type === 'partner' ? 'required|string|max:60' : 'nullable',
            'marketplace' => $type === 'seller' ? 'required|string|max:60' : 'nullable',
            'business_name' => 'required|string|max:150',
            'contact_name' => 'required|string|max:120',
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => 'required|string|max:30',
            'city' => 'required|string|max:100',
            'country' => 'required|string|size:2|alpha',
            'description' => 'nullable|string|max:400',
            'registration_number' => 'required|string|max:40',
            'tax_number' => ($type === 'seller' ? 'required' : 'nullable').'|string|max:30',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'password' => StrongPassword::rules(confirmed: true),
            'documents' => 'required|array',
            'documents.id' => $doc('id'),
            'documents.commercial_registration' => $doc('commercial_registration'),
            'documents.tax_certificate' => $doc('tax_certificate'),
            'documents.license' => $doc('license'),
        ]);

        // Where they are applying: a store (partners) or a marketplace (sellers).
        if ($type === 'partner') {
            $store = $stores->find($data['store']) ?? throw ValidationException::withMessages(['store' => ['This store was not found.']]);
            $tenant = $store->tenant;
        } else {
            $tenant = Tenant::where('uuid', $data['marketplace'])->first();
            $open = collect($stores->marketplaces())->pluck('key');
            $store = null;

            if (! $tenant || ! $open->contains($tenant->uuid)) {
                throw ValidationException::withMessages(['marketplace' => ['This marketplace was not found.']]);
            }
        }

        if ($emails->isBlocked($data['email'])) {
            Audit::record('partner_application.blocked', null, ['reason' => 'email_domain', 'email' => $data['email']]);
            throw ValidationException::withMessages(['email' => ['Please use a permanent email address.']]);
        }

        $canonical = EmailPolicy::canonical($data['email']);
        if (User::where('email_canonical', $canonical)->exists()
            || PartnerApplication::where('email_canonical', $canonical)->whereIn('status', [PartnerApplication::PENDING, PartnerApplication::APPROVED])->exists()) {
            throw ValidationException::withMessages(['email' => ['The email has already been taken.']]);
        }

        $phone = $markets->phoneForTenant($tenant, $data['phone']) ?? throw ValidationException::withMessages(['phone' => ['Please enter a valid mobile number.']]);

        // The tax number is checked for shape against the country's format.
        if (! empty($data['tax_number']) && ($market = $markets->forTenant($tenant)) && ! $market->validTaxId($data['tax_number'])) {
            throw ValidationException::withMessages(['tax_number' => ['This does not look like a valid tax registration number.']]);
        }

        try {
            $application = DB::transaction(function () use ($data, $type, $store, $tenant, $canonical, $phone, $request, $documents) {
                $app = PartnerApplication::create([
                    'type' => $type, 'company_id' => $store?->id, 'tenant_id' => $tenant->id, 'business_name' => $data['business_name'], 'contact_name' => $data['contact_name'],
                    'email' => mb_strtolower(trim($data['email'])), 'email_canonical' => $canonical, 'phone' => $phone, 'city' => $data['city'],
                    'country' => strtoupper($data['country']), 'description' => $data['description'] ?? null, 'registration_number' => $data['registration_number'],
                    'tax_number' => $data['tax_number'] ?? null, 'latitude' => $data['latitude'] ?? null, 'longitude' => $data['longitude'] ?? null,
                    'password' => Hash::make($data['password']), 'status' => PartnerApplication::PENDING,
                ]);

                foreach (ApplicationDocuments::KINDS as $kind) {
                    if ($upload = $request->file("documents.{$kind}")) {
                        $documents->store($app, $kind, $upload);
                    }
                }

                return $app;
            });
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['documents' => [$e->getMessage()]]);
        } catch (\RuntimeException $e) {
            report($e);

            return response()->json(['message' => 'We could not save your documents right now. Please try again in a moment.'], 503);
        }

        Audit::record('partner_application.submitted', $application, ['type' => $type]);

        // Tell the people who can decide: sellers go to platform admins; partners also to the store's owners.
        $reviewers = User::role('platform-admin')->get();
        if ($store) {
            $reviewers = $reviewers->merge(User::role('company-owner')->where('company_id', $store->id)->get());
        }
        foreach ($reviewers->unique('id')->where('is_active', true) as $reviewer) {
            try {
                $reviewer->notify(new PartnerApplicationReceived($application->business_name, $store?->name ?? $tenant->name, $type));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json(['message' => 'Application received. You can sign in as soon as it is approved.'], 201);
    }

    /** Admins see everything; a store's owner sees only partner applications to their own store. */
    public function index(Request $request)
    {
        $request->validate(['status' => 'nullable|in:pending,approved,rejected,all', 'type' => 'nullable|in:partner,seller']);
        $user = $this->reviewer($request);
        $status = $request->query('status', 'pending');

        return PartnerApplication::with(['store:id,name,slug', 'documents'])
            ->when(! $user->hasRole('platform-admin'), fn ($q) => $q->where('type', 'partner')->where('company_id', $user->company_id))
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->query('type')))
            ->latest('id')->paginate(20)
            ->through(fn (PartnerApplication $a) => $a->setAttribute('marketplace', Tenant::find($a->tenant_id)?->name)
                ->setAttribute('missing_documents', array_values(array_diff(ApplicationDocuments::required($a->type), $a->documents->pluck('kind')->all()))));
    }

    /** A reviewer opens one document. Every view is recorded. */
    public function document(Request $request, int $id, int $documentId, ApplicationDocuments $documents)
    {
        $application = $this->decidable($request, $id);
        $document = ApplicationDocument::where('application_id', $application->id)->findOrFail($documentId);

        Audit::record('partner_application.document_viewed', $application, ['document' => $document->kind]);

        return $documents->download($document);
    }

    public function approve(Request $request, int $id, PartnerApplicationService $service)
    {
        $app = $this->decidable($request, $id);
        $request->validate(['documents_verified' => 'accepted']);

        // Never approve a business whose required documents are not all on file.
        $missing = array_diff(ApplicationDocuments::required($app->type), $app->documents()->pluck('kind')->all());
        if ($missing) {
            throw ValidationException::withMessages(['documents' => ['Required documents are missing: '.implode(', ', $missing)]]);
        }

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

        // A store's owner can only decide on partner applications to their own store; to anyone else it does not exist.
        abort_unless($user->hasRole('platform-admin') || ($app->type === 'partner' && $app->company_id === $user->company_id), 404);

        return $app;
    }
}
