<?php

namespace App\Http\Controllers\Api;

use App\Enums\QuoteStatus;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CommissionDistribution;
use App\Models\Order;
use App\Models\Partner;
use App\Models\PartnerQuote;
use App\Models\User;
use App\Models\Warranty;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    private const REVENUE_STATUSES = ['paid', 'appointment_scheduled', 'service_in_progress', 'completed'];

    /** Role-appropriate numbers for the home screen. */
    public function stats(Request $request)
    {
        $user = $request->user();

        if ($user->hasRole('platform-admin')) {
            return $this->admin();
        }

        if ($user->hasRole('company-owner')) {
            return $this->owner();
        }

        if ($user->hasAnyRole(['partner-manager', 'technician']) && $user->partner_id) {
            return $this->partner($user);
        }

        return $this->customer($user);
    }

    private function admin(): array
    {
        return $this->response('platform-admin', [
            $this->card('orders', 'Orders', Order::count()),
            $this->card('revenue', 'Revenue', $this->revenue(Order::query()), 'money'),
            $this->card('customers', 'Customers', User::role('customer')->count()),
            $this->card('partners', 'Partners', Partner::count()),
            $this->card('companies', 'Companies', Company::count()),
            $this->card('pending_payouts', 'Payouts pending', CommissionDistribution::where('status', 'pending')->sum('amount'), 'money'),
        ], Order::query());
    }

    private function owner(): array
    {
        return $this->response('company-owner', [
            $this->card('orders', 'Orders', Order::count()),
            $this->card('revenue', 'Revenue', $this->revenue(Order::query()), 'money'),
            $this->card('team', 'Team members', User::where('company_id', auth()->user()->company_id)->count()),
            $this->card('open', 'Awaiting payment or quotes', Order::whereIn('status', ['pending_quotes', 'quotes_received', 'partner_selected', 'awaiting_payment'])->count()),
        ], Order::query());
    }

    private function partner(User $user): array
    {
        $jobIds = PartnerQuote::withoutGlobalScopes()
            ->where('partner_id', $user->partner_id)->where('status', QuoteStatus::Accepted)->pluck('order_id');
        $jobs = Order::withoutGlobalScopes()->whereIn('id', $jobIds);

        $open = PartnerQuote::withoutGlobalScopes()
            ->where('partner_id', $user->partner_id)
            ->whereIn('status', [QuoteStatus::Pending, QuoteStatus::Submitted])
            ->whereHas('order', fn ($q) => $q->withoutGlobalScopes()->whereIn('status', ['pending_quotes', 'quotes_received']))
            ->count();

        $earnings = fn (string $status) => CommissionDistribution::where('partner_id', $user->partner_id)->where('status', $status)->sum('amount');

        return $this->response('partner', [
            $this->card('open_requests', 'Open requests', $open),
            $this->card('to_schedule', 'To schedule', (clone $jobs)->where('status', 'paid')->count()),
            $this->card('in_progress', 'In progress', (clone $jobs)->whereIn('status', ['appointment_scheduled', 'service_in_progress'])->count()),
            $this->card('completed', 'Completed', (clone $jobs)->where('status', 'completed')->count()),
            $this->card('earnings_pending', 'Earnings pending', $earnings('pending'), 'money'),
            $this->card('earnings_paid', 'Earnings paid', $earnings('paid'), 'money'),
        ], $jobs);
    }

    private function customer(User $user): array
    {
        $mine = Order::where('customer_id', $user->id);

        return $this->response('customer', [
            $this->card('orders', 'My orders', (clone $mine)->count()),
            $this->card('active', 'In progress', (clone $mine)->whereNotIn('status', ['completed', 'cancelled'])->count()),
            $this->card('spent', 'Spent', $this->revenue(clone $mine), 'money'),
            $this->card('warranties', 'Active warranties', Warranty::where('customer_id', $user->id)->where('ends_at', '>', now())->count()),
        ], $mine);
    }

    private function revenue($orders): float
    {
        return (float) $orders->whereIn('status', self::REVENUE_STATUSES)->sum('total_amount');
    }

    private function card(string $key, string $label, $value, string $format = 'number'): array
    {
        return ['key' => $key, 'label' => $label, 'value' => round((float) $value, 2), 'format' => $format];
    }

    private function response(string $role, array $cards, $orders): array
    {
        return [
            'role' => $role,
            'cards' => $cards,
            'orders_by_status' => (clone $orders)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
        ];
    }
}
