<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Partner;
use App\Models\PartnerApplication;
use App\Models\User;
use App\Support\Audit;
use App\Support\Security\EmailPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Turns an approved application into a working partner: company, partner profile, account and role, in one step. */
class PartnerApplicationService
{
    public const ROLE = 'partner-manager';
    public const SELLER_ROLE = 'seller';

    public function approve(PartnerApplication $application, User $by): PartnerApplication
    {
        return DB::transaction(function () use ($application, $by) {
            $app = PartnerApplication::lockForUpdate()->findOrFail($application->id);

            if ($app->status !== PartnerApplication::PENDING) {
                throw ValidationException::withMessages(['application' => ['This application has already been reviewed.']]);
            }

            // Someone may have registered with the same mailbox since the application was sent.
            if (User::where('email', $app->email)->orWhere('email_canonical', EmailPolicy::canonical($app->email))->exists()) {
                throw ValidationException::withMessages(['application' => ['An account with this email already exists.']]);
            }

            if ($app->type === 'seller') {
                return $this->approveSeller($app, $by);
            }

            $company = Company::create(['tenant_id' => $app->tenant_id, 'name' => $app->business_name, 'type' => 'partner', 'status' => 'active']);

            $partner = Partner::create([
                'company_id' => $company->id, 'name' => $app->business_name, 'city' => $app->city, 'country' => $app->country, 'phone' => $app->phone,
                'latitude' => $app->latitude, 'longitude' => $app->longitude, 'location_enabled' => $app->latitude !== null && $app->longitude !== null,
                'is_active' => true, 'description' => $app->description, 'public_profile' => false,
            ]);

            // The password the applicant chose is already hashed; it is kept as is.
            $user = User::create([
                'name' => $app->contact_name, 'email' => $app->email, 'phone' => $app->phone, 'password' => $app->password,
                'company_id' => $company->id, 'partner_id' => $partner->id, 'city' => $app->city, 'country' => $app->country,
                'email_verified_at' => now(),
            ]);
            $user->assignRole(self::ROLE);

            $app->update(['status' => PartnerApplication::APPROVED, 'reviewed_by' => $by->id, 'reviewed_at' => now(), 'partner_id' => $partner->id, 'user_id' => $user->id]);
            Audit::record('partner_application.approved', $app, ['partner_id' => $partner->id], $by);

            return $app;
        });
    }

    /** A seller gets its own company (a "supplier") and an owner account with the seller role. */
    private function approveSeller(PartnerApplication $app, User $by): PartnerApplication
    {
        $company = Company::create(['tenant_id' => $app->tenant_id, 'name' => $app->business_name, 'type' => 'supplier', 'status' => 'active',
            'legal_name' => $app->business_name, 'tax_number' => $app->tax_number, 'phone' => $app->phone, 'email' => $app->email]);

        $user = User::create([
            'name' => $app->contact_name, 'email' => $app->email, 'phone' => $app->phone, 'password' => $app->password,
            'company_id' => $company->id, 'city' => $app->city, 'country' => $app->country, 'email_verified_at' => now(),
        ]);
        $user->assignRole(self::SELLER_ROLE);

        $app->update(['status' => PartnerApplication::APPROVED, 'reviewed_by' => $by->id, 'reviewed_at' => now(), 'user_id' => $user->id]);
        Audit::record('partner_application.approved', $app, ['type' => 'seller', 'company_id' => $company->id], $by);

        return $app;
    }

    public function reject(PartnerApplication $application, User $by, ?string $reason): PartnerApplication
    {
        return DB::transaction(function () use ($application, $by, $reason) {
            $app = PartnerApplication::lockForUpdate()->findOrFail($application->id);

            if ($app->status !== PartnerApplication::PENDING) {
                throw ValidationException::withMessages(['application' => ['This application has already been reviewed.']]);
            }

            $app->update(['status' => PartnerApplication::REJECTED, 'reviewed_by' => $by->id, 'reviewed_at' => now(), 'rejection_reason' => $reason]);
            Audit::record('partner_application.rejected', $app, ['reason' => $reason], $by);

            return $app;
        });
    }
}
