<?php

namespace App\Services;

use App\Core\Events\Event;
use App\Core\Events\EventBus;
use App\Models\Company;
use App\Models\SellerLedgerEntry;
use App\Models\SellerPayout;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** What sellers are owed and how it is paid out. Money never moves here: an admin pays by bank and records the reference. */
class SellerPayoutService
{
    public function __construct(private EventBus $events)
    {
    }

    /** @return array<string, array{held: float, available: float, in_payout: float, paid: float}> per currency */
    public function balances(int $companyId): array
    {
        $out = [];

        foreach (SellerLedgerEntry::where('company_id', $companyId)->get() as $e) {
            $b = &$out[$e->currency];
            $b ??= ['held' => 0.0, 'available' => 0.0, 'in_payout' => 0.0, 'paid' => 0.0];
            $key = match (true) {
                $e->status === 'held' => 'held',
                $e->status === 'available' && $e->payout_id !== null => 'in_payout',
                $e->status === 'available' => 'available',
                default => 'paid',
            };
            $b[$key] = round($b[$key] + (float) $e->amount, 2);
            unset($b);
        }

        return $out;
    }

    /** Gathers everything the seller can be paid right now into one payout. */
    public function create(Company $seller, string $currency, User $by): SellerPayout
    {
        return DB::transaction(function () use ($seller, $currency, $by) {
            $entries = SellerLedgerEntry::where('company_id', $seller->id)->where('currency', $currency)->where('status', 'available')->whereNull('payout_id')->lockForUpdate()->get();
            $total = round((float) $entries->sum('amount'), 2);

            if ($entries->isEmpty() || $total <= 0) {
                throw ValidationException::withMessages(['payout' => ['Nothing is available to pay out for this seller.']]);
            }

            $payout = SellerPayout::create(['company_id' => $seller->id, 'amount' => $total, 'currency' => $currency, 'status' => 'pending', 'created_by' => $by->id]);
            SellerLedgerEntry::whereIn('id', $entries->pluck('id'))->update(['payout_id' => $payout->id]);

            $this->events->publish(new Event('payout.created', ['payout_id' => $payout->id, 'company_id' => $seller->id, 'amount' => $total, 'currency' => $currency],
                $seller->tenant_id, 'payout', $payout->id, 'user', $by->id));

            return $payout;
        });
    }

    public function markPaid(SellerPayout $payout, string $reference, User $by): SellerPayout
    {
        return DB::transaction(function () use ($payout, $reference, $by) {
            $locked = SellerPayout::lockForUpdate()->findOrFail($payout->id);

            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages(['payout' => ['This payout has already been paid.']]);
            }

            $locked->update(['status' => 'paid', 'reference' => $reference, 'paid_by' => $by->id, 'paid_at' => now()]);
            SellerLedgerEntry::where('payout_id', $locked->id)->update(['status' => 'paid']);

            $this->events->publish(new Event('payout.paid', ['payout_id' => $locked->id, 'company_id' => $locked->company_id, 'amount' => (float) $locked->amount, 'currency' => $locked->currency, 'reference' => $reference],
                $locked->company->tenant_id, 'payout', $locked->id, 'user', $by->id));

            return $locked;
        });
    }
}
