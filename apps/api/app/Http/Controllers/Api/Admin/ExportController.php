<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\SellerPayout;
use App\Models\User;
use App\Support\Audit;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The records an administrator may need outside the platform (accounts, reconciliation), as CSV. Every export is written to the audit log. */
class ExportController extends Controller
{
    private const KINDS = ['orders', 'payments', 'payouts', 'users'];

    public function __invoke(Request $request, string $kind): StreamedResponse
    {
        abort_unless(in_array($kind, self::KINDS, true), 404);
        $data = $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from']);
        $from = isset($data['from']) ? CarbonImmutable::parse($data['from'])->startOfDay() : null;
        $to = isset($data['to']) ? CarbonImmutable::parse($data['to'])->endOfDay() : null;

        [$headers, $rows] = match ($kind) {
            'orders' => $this->orders($from, $to),
            'payments' => $this->payments($from, $to),
            'payouts' => $this->payouts($from, $to),
            'users' => $this->users($from, $to),
        };

        Audit::record('admin.exported', null, ['kind' => $kind, 'from' => $from?->toDateString(), 'to' => $to?->toDateString()], $request->user());
        $file = "{$kind}-".now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // so Excel reads Arabic names correctly
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, array_map([$this, 'safe'], $row));
            }
            fclose($out);
        }, $file, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** A cell that starts with = + - @ would run as a formula when opened in a spreadsheet: it is written as plain text instead. */
    private function safe(mixed $value): string
    {
        $text = $value instanceof \BackedEnum ? (string) $value->value : (string) ($value ?? '');

        return $text !== '' && in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true) && ! is_numeric($text) ? "'".$text : $text;
    }

    /** @return array{0: list<string>, 1: iterable<list<mixed>>} */
    private function orders(?CarbonImmutable $from, ?CarbonImmutable $to): array
    {
        $q = Order::withoutGlobalScopes()->with(['product:id,name', 'customer:id,email'])->when($from, fn ($q) => $q->where('created_at', '>=', $from))->when($to, fn ($q) => $q->where('created_at', '<=', $to))->orderBy('id');

        return [['id', 'created_at', 'market', 'status', 'product', 'customer', 'quantity', 'total_amount', 'currency', 'platform_commission', 'refund_amount'],
            $q->lazy()->map(fn ($o) => [$o->id, $o->created_at?->toDateTimeString(), $o->market, $o->status, $o->product?->name, $o->customer?->email ?? $o->guest_email, $o->quantity, $o->total_amount, $o->currency, $o->commission_amount, $o->refund_amount])];
    }

    /** @return array{0: list<string>, 1: iterable<list<mixed>>} */
    private function payments(?CarbonImmutable $from, ?CarbonImmutable $to): array
    {
        $q = Payment::withoutGlobalScopes()->when($from, fn ($q) => $q->where('created_at', '>=', $from))->when($to, fn ($q) => $q->where('created_at', '<=', $to))->orderBy('id');

        return [['id', 'created_at', 'order_id', 'status', 'provider', 'method', 'transaction_id', 'amount', 'currency'],
            $q->lazy()->map(fn ($p) => [$p->id, $p->created_at?->toDateTimeString(), $p->order_id, $p->status, $p->provider, $p->payment_method, $p->transaction_id, $p->amount, $p->currency])];
    }

    /** @return array{0: list<string>, 1: iterable<list<mixed>>} */
    private function payouts(?CarbonImmutable $from, ?CarbonImmutable $to): array
    {
        $q = SellerPayout::with('company:id,name')->when($from, fn ($q) => $q->where('created_at', '>=', $from))->when($to, fn ($q) => $q->where('created_at', '<=', $to))->orderBy('id');

        return [['id', 'created_at', 'seller', 'status', 'amount', 'currency', 'reference', 'paid_at'],
            $q->lazy()->map(fn ($p) => [$p->id, $p->created_at?->toDateTimeString(), $p->company?->name, $p->status, $p->amount, $p->currency, $p->reference, $p->paid_at?->toDateTimeString()])];
    }

    /** @return array{0: list<string>, 1: iterable<list<mixed>>} */
    private function users(?CarbonImmutable $from, ?CarbonImmutable $to): array
    {
        $q = User::query()->with(['roles:id,name', 'company:id,name'])->where(fn ($w) => $w->where('is_guest', false)->orWhereNull('is_guest'))
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->addSelect(['last_login_at' => AuditLog::query()->selectRaw('max(created_at)')->whereColumn('audit_logs.user_id', 'users.id')->where('audit_logs.action', 'auth.login')])->orderBy('id');

        return [['id', 'name', 'email', 'role', 'company', 'active', 'created_at', 'last_login_at'],
            $q->lazy()->map(fn ($u) => [$u->id, $u->name, $u->email, $u->roles->pluck('name')->implode('|'), $u->company?->name, $u->is_active ? 'yes' : 'no', $u->created_at?->toDateTimeString(), $u->last_login_at])];
    }
}
