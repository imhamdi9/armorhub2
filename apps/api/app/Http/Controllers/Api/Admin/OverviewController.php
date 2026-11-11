<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Commission;
use App\Models\CommissionDistribution;
use App\Models\Company;
use App\Models\Order;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payment\PaymentProviders;
use App\Support\SecurityChecks;

/** The admin home screen: numbers, things that need attention, and the latest activity. */
class OverviewController extends Controller
{
    private const REVENUE = ['paid', 'appointment_scheduled', 'service_in_progress', 'completed'];

    public function __invoke(SecurityChecks $checks, PaymentProviders $providers)
    {
        $since = now()->subDay();
        $audit = fn (array $actions) => AuditLog::whereIn('action', $actions)->where('created_at', '>=', $since)->count();
        $health = collect($checks->run())->countBy('status');

        $attention = collect([
            ['key' => 'security_failures', 'label' => 'Security checks failing', 'count' => (int) $health->get('fail', 0), 'path' => '/dashboard/admin/security', 'severity' => 'fail'],
            ['key' => 'security_warnings', 'label' => 'Security warnings', 'count' => (int) $health->get('warn', 0), 'path' => '/dashboard/admin/security', 'severity' => 'warn'],
            ['key' => 'payment_issues', 'label' => 'Payment webhooks rejected or mismatched (24h)', 'count' => $audit(['payments.webhook_rejected', 'payments.webhook_mismatch']), 'path' => '/dashboard/admin/audit', 'severity' => 'fail'],
            ['key' => 'stuck_payments', 'label' => 'Payments pending for over an hour', 'count' => Payment::withoutGlobalScopes()->where('status', 'pending')->where('created_at', '<', now()->subHour())->count(), 'path' => '/dashboard/admin/payments', 'severity' => 'warn'],
            ['key' => 'failed_logins', 'label' => 'Failed sign-ins (24h)', 'count' => $audit(['auth.login_failed']), 'path' => '/dashboard/admin/security', 'severity' => 'warn'],
            ['key' => 'unpaid_payouts', 'label' => 'Commission payouts waiting', 'count' => CommissionDistribution::where('status', 'pending')->count(), 'path' => '/dashboard/commissions', 'severity' => 'info'],
        ])->filter(fn ($a) => $a['count'] > 0)->values();

        $revenue = (float) Order::query()->whereIn('status', self::REVENUE)->sum('total_amount');

        return [
            'numbers' => [
                ['key' => 'revenue', 'label' => 'Revenue', 'value' => round($revenue, 2), 'format' => 'money'],
                ['key' => 'commission', 'label' => 'Platform commission', 'value' => round((float) Commission::sum('amount'), 2), 'format' => 'money'],
                ['key' => 'orders', 'label' => 'Orders', 'value' => Order::count(), 'format' => 'number'],
                ['key' => 'customers', 'label' => 'Customers', 'value' => User::role('customer')->count(), 'format' => 'number'],
                ['key' => 'new_accounts', 'label' => 'New accounts (24h)', 'value' => $audit(['auth.registered']), 'format' => 'number'],
                ['key' => 'partners', 'label' => 'Partners', 'value' => Partner::count(), 'format' => 'number'],
                ['key' => 'companies', 'label' => 'Companies', 'value' => Company::count(), 'format' => 'number'],
                ['key' => 'staff', 'label' => 'Staff accounts', 'value' => User::whereDoesntHave('roles', fn ($q) => $q->where('name', 'customer'))->count(), 'format' => 'number'],
            ],
            'orders_by_status' => Order::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            'payments' => [
                'provider' => $providers->active()->label(),
                'provider_key' => $providers->activeKey(),
                'configured' => $providers->isConfigured($providers->activeKey()),
            ],
            'attention' => $attention,
            'recent_activity' => AuditLog::with('user:id,name')->latest('id')->limit(10)->get()->map(fn ($l) => [
                'id' => $l->id, 'action' => $l->action, 'user' => $l->user?->name, 'subject' => $l->subject_type ? "{$l->subject_type}#{$l->subject_id}" : null, 'created_at' => $l->created_at,
            ]),
        ];
    }
}
