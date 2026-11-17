<?php

namespace App\Services;

use App\Models\Company;
use App\Models\InstallationOffer;
use App\Models\InstallationPayment;
use App\Models\InstallationRequest;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\ProductQuote;
use App\Models\ProductQuoteRequest;
use App\Support\Markets\Markets;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The numbers that say whether the business works: what the platform handles (GMV), what it keeps (net revenue and take rate), whether
 * requests get answered and quotes get accepted, whether orders finish and customers come back. Money is never added across currencies: each
 * figure is per currency (a market is a currency). Every figure comes from records, never from a counter that could drift.
 */
class Kpis
{
    /** The pilot's own tests of whether the model works, and the numbers it should reach (the board's conditions). */
    public const PILOT = ['centres' => 15, 'sellers' => 20, 'requests_per_month' => 100, 'answered_share' => 70.0, 'executed_share' => 25.0];

    /** Orders that were paid, whatever happened after. */
    private const PAID = ['paid', 'appointment_scheduled', 'service_in_progress', 'completed'];

    public function __construct(private Markets $markets)
    {
    }

    /** @return array<string, mixed> */
    public function compute(CarbonImmutable $from, CarbonImmutable $to, ?string $market = null): array
    {
        $currency = $market ? $this->currencyOf($market) : null;
        $out = [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'market' => $market],
            'gmv' => $this->gmv($from, $to, $market),
            'net_revenue' => $this->netRevenue($from, $to, $currency),
        ];

        $out['take_rate'] = collect($out['net_revenue']['total'])->map(fn ($net, $cur) => ($out['gmv']['total'][$cur] ?? 0) > 0 ? round($net / $out['gmv']['total'][$cur] * 100, 2) : null)->all();
        $out['average_order_value'] = $this->aov($from, $to, $market);
        $out['active'] = ['sellers' => $this->activeSellers($from, $to, $market), 'centres' => $this->activeCentres($from, $to, $market)];
        $out['rfq'] = $this->rfq($from, $to, $market);
        $out['orders'] = $this->completion($from, $to, $market);
        $out['repeat_purchase_rate'] = $this->repeat($to, $market);
        $out['pilot'] = $this->pilot($to, $market);

        return $out;
    }

    private function currencyOf(string $market): ?string
    {
        return $this->markets->get($market)?->currency();
    }

    /** What customers paid through the platform in the period, by currency and by kind (goods and installation). */
    private function gmv(CarbonImmutable $from, CarbonImmutable $to, ?string $market): array
    {
        $goods = Payment::withoutGlobalScopes()->where('status', 'completed')->whereBetween('payments.created_at', [$from->startOfDay(), $to->endOfDay()])
            ->when($market, fn ($q) => $q->whereIn('order_id', Order::withoutGlobalScopes()->where('market', $market)->select('id')))
            ->selectRaw('currency, sum(amount) as total')->groupBy('currency')->pluck('total', 'currency')->map(fn ($v) => round((float) $v, 2))->all();

        $jobs = InstallationPayment::where('status', 'completed')->whereBetween('installation_payments.created_at', [$from->startOfDay(), $to->endOfDay()])
            ->when($market, fn ($q) => $q->whereIn('request_id', InstallationRequest::where('market', $market)->select('id')))
            ->selectRaw('currency, sum(amount) as total')->groupBy('currency')->pluck('total', 'currency')->map(fn ($v) => round((float) $v, 2))->all();

        $total = [];
        foreach ([$goods, $jobs] as $part) {
            foreach ($part as $cur => $v) {
                $total[$cur] = round(($total[$cur] ?? 0) + $v, 2);
            }
        }

        return ['total' => $total, 'orders' => $goods, 'installation' => $jobs];
    }

    /** What the platform kept: its revenue accounts in the ledger, by stream and currency. */
    private function netRevenue(CarbonImmutable $from, CarbonImmutable $to, ?string $currency): array
    {
        $rows = LedgerEntry::query()->join('ledger_accounts', 'ledger_accounts.id', '=', 'ledger_entries.account_id')->where('ledger_accounts.type', 'revenue')
            ->whereBetween('ledger_entries.posted_at', [$from->startOfDay(), $to->endOfDay()])->when($currency, fn ($q) => $q->where('ledger_entries.currency', $currency))
            ->selectRaw('ledger_accounts.code as code, ledger_entries.currency as currency, sum(ledger_entries.credit) - sum(ledger_entries.debit) as net')->groupBy('ledger_accounts.code', 'ledger_entries.currency')->get();

        $streams = [];
        $total = [];
        foreach ($rows as $r) {
            $key = str_replace('revenue:', '', $r->code);
            $streams[$key][$r->currency] = round((float) $r->net, 2);
            $total[$r->currency] = round(($total[$r->currency] ?? 0) + (float) $r->net, 2);
        }

        return ['total' => $total, 'streams' => $streams];
    }

    /** Value of an average purchase: what was paid divided by the purchases (orders and installation jobs). @return array<string, float> */
    private function aov(CarbonImmutable $from, CarbonImmutable $to, ?string $market): array
    {
        $gmv = $this->gmv($from, $to, $market)['total'];
        $count = Payment::withoutGlobalScopes()->where('status', 'completed')->whereBetween('payments.created_at', [$from->startOfDay(), $to->endOfDay()])
            ->when($market, fn ($q) => $q->whereIn('order_id', Order::withoutGlobalScopes()->where('market', $market)->select('id')))->selectRaw('currency, count(*) as n')->groupBy('currency')->pluck('n', 'currency')->all();
        foreach (InstallationPayment::where('status', 'completed')->whereBetween('installation_payments.created_at', [$from->startOfDay(), $to->endOfDay()])
            ->when($market, fn ($q) => $q->whereIn('request_id', InstallationRequest::where('market', $market)->select('id')))->selectRaw('currency, count(*) as n')->groupBy('currency')->pluck('n', 'currency') as $cur => $n) {
            $count[$cur] = ($count[$cur] ?? 0) + $n;
        }

        return collect($gmv)->map(fn ($v, $cur) => ($count[$cur] ?? 0) > 0 ? round($v / $count[$cur], 2) : null)->all();
    }

    /** Sellers that sold something in the period. */
    private function activeSellers(CarbonImmutable $from, CarbonImmutable $to, ?string $market): int
    {
        return Order::withoutGlobalScopes()->join('products', 'products.id', '=', 'orders.product_id')->whereNotNull('products.seller_id')->whereIn('orders.status', self::PAID)
            ->whereBetween('orders.updated_at', [$from->startOfDay(), $to->endOfDay()])->when($market, fn ($q) => $q->where('orders.market', $market))->distinct()->count('products.seller_id');
    }

    /** Centres that did paid jobs in the period. */
    private function activeCentres(CarbonImmutable $from, CarbonImmutable $to, ?string $market): int
    {
        return InstallationOffer::whereIn('id', InstallationRequest::whereNotNull('paid_at')->whereBetween('paid_at', [$from->startOfDay(), $to->endOfDay()])
            ->when($market, fn ($q) => $q->where('market', $market))->select('selected_offer_id'))->distinct()->count('partner_id');
    }

    /** Whether requests get answered, and whether quotes become orders. @return array<string, mixed> */
    private function rfq(CarbonImmutable $from, CarbonImmutable $to, ?string $market): array
    {
        $jobs = InstallationRequest::whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])->when($market, fn ($q) => $q->where('market', $market));
        $jobCount = (clone $jobs)->count();
        $jobAnswered = (clone $jobs)->whereIn('id', InstallationOffer::select('request_id'))->count();
        $jobAccepted = (clone $jobs)->whereIn('id', InstallationOffer::select('request_id'))->whereNotNull('paid_at')->count();

        $prod = ProductQuoteRequest::whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])->when($market, fn ($q) => $q->whereIn('company_id', $this->companiesOf($market)));
        $prodCount = (clone $prod)->count();
        $prodAnswered = (clone $prod)->whereIn('id', ProductQuote::select('request_id'))->count();
        $prodAccepted = (clone $prod)->where('status', 'accepted')->count();

        $pct = fn (int $part, int $whole) => $whole > 0 ? round($part / $whole * 100, 1) : null;

        return [
            'requests' => $jobCount + $prodCount,
            'response_rate' => $pct($jobAnswered + $prodAnswered, $jobCount + $prodCount),
            'quote_conversion' => $pct($jobAccepted + $prodAccepted, $jobAnswered + $prodAnswered),
            'installation' => ['requests' => $jobCount, 'answered' => $jobAnswered, 'converted' => $jobAccepted],
            'products' => ['requests' => $prodCount, 'answered' => $prodAnswered, 'converted' => $prodAccepted],
        ];
    }

    /** Of the orders that were paid, how many were finished; the same for installation jobs. @return array<string, mixed> */
    private function completion(CarbonImmutable $from, CarbonImmutable $to, ?string $market): array
    {
        $orders = Order::withoutGlobalScopes()->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])->when($market, fn ($q) => $q->where('market', $market));
        $paid = (clone $orders)->whereIn('status', self::PAID)->count();
        $done = (clone $orders)->where('status', 'completed')->count();

        $jobs = InstallationRequest::whereNotNull('paid_at')->whereBetween('paid_at', [$from->startOfDay(), $to->endOfDay()])->when($market, fn ($q) => $q->where('market', $market));
        $jobsPaid = (clone $jobs)->count();
        $jobsDone = (clone $jobs)->where('status', 'released')->count();

        $pct = fn (int $part, int $whole) => $whole > 0 ? round($part / $whole * 100, 1) : null;

        return ['paid' => $paid + $jobsPaid, 'completed' => $done + $jobsDone, 'completion_rate' => $pct($done + $jobsDone, $paid + $jobsPaid),
            'goods' => ['paid' => $paid, 'completed' => $done], 'installation' => ['paid' => $jobsPaid, 'completed' => $jobsDone]];
    }

    /** Of customers who ever bought, the share who bought twice or more (up to the end of the period). */
    private function repeat(CarbonImmutable $to, ?string $market): ?float
    {
        $orders = Order::withoutGlobalScopes()->whereNotNull('customer_id')->whereIn('status', self::PAID)->where('created_at', '<=', $to->endOfDay())->when($market, fn ($q) => $q->where('market', $market))
            ->selectRaw('customer_id, count(*) as n')->groupBy('customer_id')->pluck('n', 'customer_id');
        $jobs = InstallationRequest::whereNotNull('paid_at')->where('paid_at', '<=', $to->endOfDay())->when($market, fn ($q) => $q->where('market', $market))
            ->selectRaw('customer_id, count(*) as n')->groupBy('customer_id')->pluck('n', 'customer_id');

        $by = [];
        foreach ([$orders, $jobs] as $set) {
            foreach ($set as $customer => $n) {
                $by[$customer] = ($by[$customer] ?? 0) + $n;
            }
        }

        return $by === [] ? null : round(count(array_filter($by, fn ($n) => $n >= 2)) / count($by) * 100, 1);
    }

    /** The five tests of the pilot: do the centres, the sellers and the requests come, and do requests get answered and done. @return array<string, mixed> */
    private function pilot(CarbonImmutable $to, ?string $market): array
    {
        $since = $to->subDays(30)->startOfDay();
        $until = $to->endOfDay();
        $requests = InstallationRequest::whereBetween('created_at', [$since, $until])->when($market, fn ($q) => $q->where('market', $market));
        $count = (clone $requests)->count();
        $answered = (clone $requests)->whereIn('id', InstallationOffer::select('request_id')->groupBy('request_id')->havingRaw('count(*) >= 2'))->count();
        $executed = (clone $requests)->whereIn('status', ['paid', 'in_progress', 'proof_submitted', 'released'])->count();

        $companies = $market ? $this->companiesOf($market) : null;
        $centres = Partner::withoutGlobalScopes()->where('is_active', true)->when($companies, fn ($q) => $q->whereIn('company_id', $companies))->count();
        $sellers = Company::withoutGlobalScopes()->where('type', 'supplier')->where('status', 'active')->when($market, fn ($q) => $q->whereIn('tenant_id', $this->tenantsOf($market)))->count();

        $t = self::PILOT;
        $row = fn (float|int|null $value, float|int $target) => ['value' => $value, 'target' => $target, 'met' => $value !== null && $value >= $target];

        return [
            'centres' => $row($centres, $t['centres']), 'sellers' => $row($sellers, $t['sellers']), 'requests_per_month' => $row($count, $t['requests_per_month']),
            'answered_share' => $row($count > 0 ? round($answered / $count * 100, 1) : null, $t['answered_share']),       // requests with at least two offers
            'executed_share' => $row($count > 0 ? round($executed / $count * 100, 1) : null, $t['executed_share']),
        ];
    }

    private function companiesOf(string $market)
    {
        return Company::withoutGlobalScopes()->whereIn('tenant_id', $this->tenantsOf($market))->pluck('id');
    }

    private function tenantsOf(string $market)
    {
        $default = $this->markets->default()?->code;

        return \App\Models\Tenant::all()->filter(fn ($t) => ($t->market ?: $default) === $market)->pluck('id');
    }
}
