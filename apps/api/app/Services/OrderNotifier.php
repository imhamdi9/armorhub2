<?php

namespace App\Services;

use App\Enums\QuoteStatus;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\Partner;
use App\Models\PartnerQuote;
use App\Models\User;
use App\Notifications\OrderUpdate;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Turns order events into notifications for the right people.
 * Never throws: a mail outage must not break an order.
 */
class OrderNotifier
{
    public function handle(OrderEvent $event): void
    {
        try {
            $order = Order::withoutGlobalScopes()->with('product')->find($event->order_id);

            if (! $order) {
                return;
            }

            foreach ($this->messages($event, $order) as [$users, $kind, $title, $body, $path]) {
                $this->send($users, new OrderUpdate($order->id, $kind, $title, $body, $path), $event->user_id);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** @return list<array{Collection<int, User>, string, string, string, string}> */
    private function messages(OrderEvent $event, Order $order): array
    {
        $name = $order->product?->name ?? 'your order';
        $customer = $this->customer($order);
        $assigned = $this->partnerStaff($this->acceptedPartnerIds($order));

        return match ($event->type) {
            'created' => [[
                $this->partnerStaff($this->askedPartnerIds($order), ['partner-manager']),
                'quote_requested', 'New quote request', "A customer wants a quote for {$name} (order #{$order->id}).", '/dashboard/requests',
            ]],
            'quote_submitted' => [[
                $customer, 'quote_received', 'You received a quote',
                ($this->partnerName($event->data['partner_id'] ?? null) ?? 'A partner')." quoted {$event->data['price']} for {$name}.",
                "/dashboard/orders/{$order->id}",
            ]],
            'quote_accepted' => [[
                $this->partnerStaff([$event->data['partner_id'] ?? 0], ['partner-manager']),
                'quote_accepted', 'Your quote was accepted', "The customer accepted your quote for {$name} (order #{$order->id}). Payment is next.", '/dashboard/requests',
            ]],
            'paid' => [
                [$assigned, 'job_ready', 'New paid job', "Order #{$order->id} ({$name}) is paid. Please schedule it.", '/dashboard/jobs'],
                [$customer, 'payment_received', 'Payment received', "We received your payment for {$name}.", "/dashboard/orders/{$order->id}"],
            ],
            'scheduled' => [[
                $customer, 'scheduled', 'Appointment scheduled', "Your appointment for {$name} is set.", "/dashboard/orders/{$order->id}",
            ]],
            'started' => [[
                $customer, 'started', 'Work has started', "Work on {$name} has started.", "/dashboard/orders/{$order->id}",
            ]],
            'completed' => [[
                $customer, 'completed', 'Work completed', "{$name} is done. Check your warranty details.", "/dashboard/orders/{$order->id}",
            ]],
            'cancelled' => [[
                $this->partnerStaff($this->askedPartnerIds($order), ['partner-manager']),
                'cancelled', 'Order cancelled', "Order #{$order->id} ({$name}) was cancelled.", '/dashboard/requests',
            ]],
            default => [],
        };
    }

    /** Skips whoever caused the event (nobody needs to be told what they just did), except receipts. */
    private function send(Collection $users, OrderUpdate $notification, ?int $actorId): void
    {
        $skipActor = $notification->kind !== 'payment_received';

        foreach ($users->reject(fn (User $u) => ($skipActor && $u->id === $actorId) || ! $u->is_active)->unique('id') as $user) {
            try {
                $user->notify($notification);
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    private function customer(Order $order): Collection
    {
        return User::whereKey($order->customer_id)->get();
    }

    /** @return list<int> */
    private function askedPartnerIds(Order $order): array
    {
        return PartnerQuote::withoutGlobalScopes()->where('order_id', $order->id)
            ->whereIn('status', [QuoteStatus::Pending, QuoteStatus::Submitted, QuoteStatus::Rejected])
            ->pluck('partner_id')->all();
    }

    /** @return list<int> */
    private function acceptedPartnerIds(Order $order): array
    {
        return PartnerQuote::withoutGlobalScopes()->where('order_id', $order->id)
            ->where('status', QuoteStatus::Accepted)->pluck('partner_id')->all();
    }

    private function partnerName(?int $id): ?string
    {
        return $id ? Partner::withoutGlobalScopes()->whereKey($id)->value('name') : null;
    }

    /**
     * @param  list<int>  $partnerIds
     * @param  list<string>  $roles
     */
    private function partnerStaff(array $partnerIds, array $roles = ['partner-manager', 'technician']): Collection
    {
        return User::whereIn('partner_id', $partnerIds)->role($roles)->get();
    }
}
