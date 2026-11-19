<?php

namespace App\Services\Payment;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Paying a whole group at once. The providers know orders by id, so a group is paid under its own id range (see OrderGroup::paymentId); when the
 * payment is confirmed every order of the group is completed exactly as if it had been paid on its own (commission, seller ledger, ledger entries).
 */
class GroupPayments
{
    public function __construct(private PaymentGateway $gateway, private PaymentFinalizer $finalizer)
    {
    }

    /** @return array{status: string, checkout_url?: string} */
    public function pay(OrderGroup $group, ?string $method = null, ?User $by = null): array
    {
        if ($group->status !== OrderGroup::AWAITING) {
            throw ValidationException::withMessages(['group' => ['This purchase is not waiting for payment.']]);
        }

        if ($method === 'cod') {
            return $this->payOnDelivery($group, $by);
        }

        $pseudo = new Order(['currency' => $group->currency, 'company_id' => $group->company_id]);
        $pseudo->id = $group->paymentId();
        $charge = $this->gateway->charge($pseudo, (float) $group->payable_amount, $method);

        if ($charge['status'] === 'failed') {
            throw ValidationException::withMessages(['payment' => [$charge['message'] ?? 'The payment was declined.']]);
        }
        if ($charge['status'] === 'redirect') {
            $group->update(['provider' => $charge['provider'] ?? null, 'transaction_id' => $charge['transaction_id']]);

            return ['status' => 'redirect', 'checkout_url' => $charge['url']];
        }

        $this->complete($group, $charge['transaction_id'] ?? null, $charge['provider'] ?? null, $by);

        return ['status' => 'paid'];
    }

    /** Whether this purchase may be paid in cash at the door: the market allows it, the amount is within the limit, and everything in it is shipped goods. */
    public function codAvailable(OrderGroup|float $group, ?bool $allGoods = null): bool
    {
        $settings = app(\App\Support\Settings\Settings::class);
        if (! $settings->get('payments.cod_enabled')) {
            return false;
        }
        $amount = $group instanceof OrderGroup ? (float) $group->payable_amount : $group;
        $max = (float) ($settings->get('payments.cod_max_amount') ?? 0);
        if ($max > 0 && $amount > $max) {
            return false;
        }
        if ($group instanceof OrderGroup) {
            $allGoods = ! $group->orders()->whereDoesntHave('product', fn ($q) => $q->where('requires_shipping', true))->exists();
        }

        return $allGoods ?? true;
    }

    /** The orders are confirmed now and the money is collected at the door; the seller can ship them. */
    public function payOnDelivery(OrderGroup $group, ?User $by = null): array
    {
        if (! $this->codAvailable($group)) {
            throw ValidationException::withMessages(['payment' => ['Cash on delivery is not available for this purchase.']]);
        }
        DB::transaction(function () use ($group, $by) {
            $locked = OrderGroup::lockForUpdate()->findOrFail($group->id);
            if ($locked->status !== OrderGroup::AWAITING) {
                return;
            }
            foreach ($locked->orders()->get() as $order) {
                $this->finalizer->complete($order, ['transaction_id' => 'COD-'.$locked->id, 'provider' => 'cod', 'cod' => true], 'cod', $by);
            }
            $locked->update(['status' => OrderGroup::PAID, 'paid_at' => now(), 'transaction_id' => 'COD-'.$locked->id, 'provider' => 'cod']);
        });

        return ['status' => 'paid'];
    }

    /** From a verified provider webhook: what was paid must be exactly what the group costs. */
    public function webhookPaid(int $groupId, string $reference, ?int $amountMinor, ?float $amountMajor, ?string $currency, string $provider): void
    {
        $group = OrderGroup::find($groupId);
        if (! $group || $group->status !== OrderGroup::AWAITING) {
            return;
        }

        $expected = Money::minorUnits((float) $group->payable_amount, $group->currency);
        $paid = $amountMinor ?? ($amountMajor !== null ? Money::minorUnits($amountMajor, $group->currency) : null);
        if ($paid !== $expected || ($currency !== null && strtoupper($currency) !== strtoupper($group->currency))) {
            Audit::record('payments.webhook_mismatch', $group, ['provider' => $provider, 'expected_minor' => $expected, 'paid_minor' => $paid, 'currency' => $currency, 'group' => true]);
            report(new \RuntimeException("Payment amount mismatch for order group {$group->id} via {$provider}."));

            return;
        }

        $this->complete($group, $reference, $provider);
    }

    public function complete(OrderGroup $group, ?string $reference, ?string $provider, ?User $by = null): ?OrderGroup
    {
        return DB::transaction(function () use ($group, $reference, $provider, $by) {
            $locked = OrderGroup::lockForUpdate()->findOrFail($group->id);
            if ($locked->status !== OrderGroup::AWAITING) {
                return null;
            }

            foreach ($locked->orders()->get() as $order) {
                if ($order->status !== OrderStatus::Paid) {
                    $this->finalizer->complete($order, ['transaction_id' => $reference, 'provider' => $provider], 'card', $by);
                }
            }
            $locked->update(['status' => OrderGroup::PAID, 'paid_at' => now(), 'transaction_id' => $reference ?? $locked->transaction_id, 'provider' => $provider ?? $locked->provider]);

            return $locked;
        });
    }
}
