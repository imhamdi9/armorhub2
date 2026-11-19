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

        // A seller whose available balance fell below zero (money refunded after it was paid out): someone has to settle it.
        $negative = \App\Models\SellerLedgerEntry::where('status', 'available')->whereNull('payout_id')->selectRaw('company_id, currency, sum(amount) as total')
            ->groupBy('company_id', 'currency')->havingRaw('sum(amount) < 0')->get()->count();

        $attention = collect([
            ['key' => 'security_failures', 'label' => 'Security checks failing', 'count' => (int) $health->get('fail', 0), 'path' => '/dashboard/admin/security', 'severity' => 'fail'],
            ['key' => 'security_warnings', 'label' => 'Security warnings', 'count' => (int) $health->get('warn', 0), 'path' => '/dashboard/admin/security', 'severity' => 'warn'],
            ['key' => 'payment_issues', 'label' => 'Payment webhooks rejected or mismatched (24h)', 'count' => $audit(['payments.webhook_rejected', 'payments.webhook_mismatch']), 'path' => '/dashboard/admin/audit', 'severity' => 'fail'],
            ['key' => 'stuck_payments', 'label' => 'Payments pending for over an hour', 'count' => Payment::withoutGlobalScopes()->where('status', 'pending')->where('created_at', '<', now()->subHour())->count(), 'path' => '/dashboard/admin/payments', 'severity' => 'warn'],
            ['key' => 'failed_logins', 'label' => 'Failed sign-ins (24h)', 'count' => $audit(['auth.login_failed']), 'path' => '/dashboard/admin/security', 'severity' => 'warn'],
            ['key' => 'applications', 'label' => 'Partner applications waiting for a decision', 'count' => \App\Models\PartnerApplication::where('status', \App\Models\PartnerApplication::PENDING)->count(), 'path' => '/dashboard/partner-applications', 'severity' => 'warn'],
            ['key' => 'product_reviews', 'label' => 'Seller products waiting for review', 'count' => \App\Models\Product::withoutGlobalScopes()->where('review_status', 'pending')->count(), 'path' => '/dashboard/admin/product-reviews', 'severity' => 'warn'],
            ['key' => 'job_disputes', 'label' => 'Installation jobs in dispute', 'count' => \App\Models\InstallationRequest::withoutGlobalScopes()->where('status', 'disputed')->count(), 'path' => '/dashboard/admin/disputes', 'severity' => 'fail'],
            ['key' => 'returns', 'label' => 'Return requests to handle', 'count' => \App\Models\OrderRefundRequest::whereIn('status', ['requested', 'awaiting_pickup', 'collected'])->count(), 'path' => '/dashboard/admin/tools', 'severity' => 'warn'],
            ['key' => 'withdrawals', 'label' => 'Centre withdrawals to pay', 'count' => \App\Models\CentreWithdrawal::where('status', 'requested')->count(), 'path' => '/dashboard/admin/withdrawals', 'severity' => 'info'],
            ['key' => 'negative_balances', 'label' => 'Sellers with a negative balance (a refund after payout)', 'count' => $negative, 'path' => '/dashboard/admin/payouts', 'severity' => 'fail'],
            ['key' => 'failed_jobs', 'label' => 'Background jobs that failed', 'count' => \Illuminate\Support\Facades\DB::table('failed_jobs')->count(), 'path' => '/dashboard/admin/system', 'severity' => 'fail'],
            ['key' => 'unpaid_payouts', 'label' => 'Commission payouts waiting', 'count' => CommissionDistribution::where('status', 'pending')->count(), 'path' => '/dashboard/commissions', 'severity' => 'info'],
        ])->filter(fn ($a) => $a['count'] > 0)->values();

        // Money is never added across currencies: each figure is shown per currency (a market is a currency).
        $round = fn ($rows) => collect($rows)->map(fn ($v) => round((float) $v, 2))->all();
        $revenue = $round(Order::query()->whereIn('status', self::REVENUE)->selectRaw('currency, sum(total_amount) as total')->groupBy('currency')->pluck('total', 'currency'));
        $commission = $round(Commission::query()->join('orders', 'orders.id', '=', 'commissions.order_id')->selectRaw('orders.currency as currency, sum(commissions.amount - commissions.refunded_amount) as total')->groupBy('orders.currency')->pluck('total', 'currency'));
        $subscriptions = $round(\App\Models\SubscriptionInvoice::where('status', 'paid')->where('paid_at', '>=', now()->startOfMonth())->selectRaw('currency, sum(amount) as total')->groupBy('currency')->pluck('total', 'currency'));

        return [
            'numbers' => [
                ['key' => 'revenue', 'label' => 'Revenue', 'value' => array_sum($revenue), 'format' => 'money', 'by_currency' => $revenue],
                ['key' => 'commission', 'label' => 'Platform commission', 'value' => array_sum($commission), 'format' => 'money', 'by_currency' => $commission],
                ['key' => 'subscription_revenue', 'label' => 'Subscription revenue (this month)', 'value' => array_sum($subscriptions), 'format' => 'money', 'by_currency' => $subscriptions],
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
