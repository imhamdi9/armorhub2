<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Industry;
use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\MarketLauncher;
use App\Support\Markets\Markets;
use Illuminate\Http\Request;

/** The platform owner opens a market in any country, names it, and chooses its industries. */
class MarketController extends Controller
{
    public function __construct(private MarketLauncher $launcher, private Markets $markets)
    {
    }

    private function row(Tenant $t): array
    {
        $store = $this->launcher->storeOf($t);
        $companies = Company::withoutGlobalScopes()->where('tenant_id', $t->id);
        $market = $this->markets->get($t->market);

        return [
            'id' => $t->id, 'name' => $t->name, 'country' => $t->market, 'country_name' => $market?->name('en'), 'country_name_ar' => $market?->name('ar'), 'currency' => $market?->currency(),
            'status' => $t->status, 'store_slug' => $store?->slug,
            'industries' => Industry::whereIn('id', $t->industryIds())->orderBy('name')->get(['id', 'key', 'name'])->all(),
            'stats' => [
                'sellers' => (clone $companies)->where('type', 'supplier')->count(), 'centres' => (clone $companies)->where('type', 'partner')->count(),
                'products' => Product::withoutGlobalScopes()->where('market', $t->market)->count(),
                'orders' => Order::withoutGlobalScopes()->whereIn('company_id', (clone $companies)->pluck('id'))->count(),
            ],
        ];
    }

    public function index()
    {
        return ['markets' => Tenant::whereNotNull('market')->where('status', '!=', 'merged')->orderBy('name')->get()->map(fn ($t) => $this->row($t))->values(),
            'industries' => Industry::orderBy('name')->get(['id', 'key', 'name'])];
    }

    private function marketCompany(Tenant $tenant): Company
    {
        return $this->launcher->storeOf($tenant) ?? abort(422, 'This market has no store company yet.');
    }

    /** The market's admins ("Egypt Market Admin"): several people can hold it, each limited to this market. */
    public function admins(Tenant $tenant)
    {
        $company = $this->marketCompany($tenant);

        return \App\Models\User::role('company-owner')->whereIn('company_id', Company::withoutGlobalScopes()->where('tenant_id', $tenant->id)->select('id'))
            ->orderBy('name')->get(['id', 'name', 'email', 'is_active'])->map(fn ($u) => $u->only(['id', 'name', 'email', 'is_active']) + ['market' => $tenant->name]);
    }

    /** Gives the market-admin role of one market to an existing person, or invites a new one. */
    public function addAdmin(Request $request, Tenant $tenant)
    {
        $company = $this->marketCompany($tenant);
        $email = mb_strtolower(trim($request->validate(['email' => 'required|email|max:255'])['email']));
        $user = \App\Models\User::where('email', $email)->first();

        if (! $user) {
            $token = \Illuminate\Support\Str::random(48);
            \App\Models\Invitation::create(['token_hash' => hash('sha256', $token), 'email' => $email, 'role' => 'company-owner', 'invited_by' => $request->user()->id,
                'company_id' => $company->id, 'expires_at' => now()->addDays(7)]);
            \Illuminate\Support\Facades\Notification::route('mail', $email)->notify(new \App\Notifications\InvitationSent($request->user()->name, $token));
            \App\Support\Audit::record('market.admin_invited', $tenant, ['email' => $email], $request->user());

            return response()->json(['invited' => true, 'email' => $email], 201);
        }
        if ($user->hasAnyRole(['platform-admin', 'customer', 'seller', 'partner-manager', 'technician'])) {
            abort(422, 'This person already works in another role. Invite a different e-mail address.');
        }
        if ($user->hasRole('company-owner') && ! in_array($user->company_id, Company::withoutGlobalScopes()->where('tenant_id', $tenant->id)->pluck('id')->all(), true)) {
            abort(422, 'This person is already an admin of another market.');
        }
        $user->update(['company_id' => $company->id, 'is_active' => true]);
        $user->syncRoles(['company-owner']);
        \App\Support\Audit::record('market.admin_added', $tenant, ['user' => $user->id], $request->user());

        return response()->json(['invited' => false, 'id' => $user->id], 201);
    }

    public function removeAdmin(Request $request, Tenant $tenant, int $userId)
    {
        $user = \App\Models\User::role('company-owner')->whereIn('company_id', Company::withoutGlobalScopes()->where('tenant_id', $tenant->id)->select('id'))->findOrFail($userId);
        $user->removeRole('company-owner');
        $user->update(['is_active' => false]);
        \App\Support\Audit::record('market.admin_removed', $tenant, ['user' => $user->id], $request->user());

        return response()->noContent();
    }

    /** Folds older per-industry markets of a country into one (the same thing the deploy does), and says exactly what went wrong if it cannot. */
    public function consolidate(\App\Services\MarketConsolidator $consolidator)
    {
        try {
            return ['folded' => $consolidator->run()];
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'Merging failed: '.$e->getMessage()], 500);
        }
    }

    /** Every country of the world, with whether ArmorHub already runs a market there. */
    public function countries()
    {
        $open = Tenant::whereNotNull('market')->pluck('market')->all();

        return collect(config('countries'))->map(fn ($c, $code) => ['code' => $code, 'name' => $c['en'], 'name_ar' => $c['ar'], 'currency' => $c['currency'], 'open' => in_array($code, $open, true)])
            ->sortBy('name')->values();
    }

    public function store(Request $request)
    {
        $data = $request->validate(['country' => 'required|string|size:2', 'name' => 'nullable|string|max:120', 'industries' => 'required|array|min:1', 'industries.*' => 'integer', 'slug' => 'nullable|string|max:80|regex:/^[a-z0-9-]+$/']);
        $country = $this->markets->get($data['country']) ?? abort(422, 'Unknown country.');
        $tenant = $this->launcher->open($data['country'], ($data['name'] ?? null) ?: 'ArmorHub '.$country->name('en'), $data['industries'], $data['slug'] ?? null, $request->user());

        return response()->json($this->row($tenant), 201);
    }

    public function update(Request $request, Tenant $tenant)
    {
        abort_unless($tenant->market, 404);
        $data = $request->validate(['name' => 'sometimes|required|string|max:120', 'industries' => 'sometimes|array|min:1', 'industries.*' => 'integer', 'status' => 'sometimes|in:active,paused']);
        $tenant = $this->launcher->update($tenant, $data['name'] ?? null, $data['industries'] ?? null, $data['status'] ?? null, $request->user());

        return $this->row($tenant);
    }
}
