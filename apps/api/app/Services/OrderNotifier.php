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
    public function __construct(private MessageTemplates $templates)
    {
    }

    public function handle(OrderEvent $event): void
    {
        try {
            $order = Order::withoutGlobalScopes()->with('product')->find($event->order_id);

            if (! $order) {
                return;
            }

            $vars = [
                'name' => $order->product?->name ?? 'your order', 'order_id' => $order->id, 'price' => $event->data['price'] ?? '',
                'partner' => $this->partnerName($event->data['partner_id'] ?? null) ?? '', 'tracking' => $event->data['tracking_number'] ?? '',
                'code' => match ($event->type) {
                    'shipment_out_for_delivery' => (string) \App\Models\Shipment::where('order_id', $order->id)->latest('id')->value('delivery_code'),
                    'return_approved' => (string) \App\Models\OrderRefundRequest::where('order_id', $order->id)->latest('id')->value('return_code'),
                    default => '',
                },
                'date' => $event->data['date'] ?? '', 'reason' => $event->data['reason'] ?? '',
            ];

            foreach ($this->messages($event, $order) as [$users, $kind, $title, $body, $path]) {
                [$title, $body] = $this->templates->render($kind, $title, $body, $vars);
                $this->send($users, $order->id, $kind, $title, $body, $path, $vars, $event->user_id);

                // Someone who bought without an account hears by e-mail, with the link to their order.
                if ($order->isGuest() && $order->guest_email && in_array($kind, ['payment_received', 'shipped', 'shipment_out_for_delivery', 'shipment_delivered', 'shipment_failed'], true)) {
                    [$gt, $gb] = $order->guest_locale === 'ar' && ArabicMessages::render($kind, $vars) && ! $this->templates->has($kind) ? ArabicMessages::render($kind, $vars) : [$title, $body];
                    \Illuminate\Support\Facades\Notification::route('mail', $order->guest_email)->notify(new \App\Notifications\GuestOrderUpdate($gt, $gb, $order->guest_token, $order->guest_locale));
                }
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
                [$this->sellerStaff($order), 'order_to_ship', 'New paid order to ship', "Order #{$order->id} ({$name}) is paid. Please ship it.", "/dashboard/seller-orders/{$order->id}"],
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
            'shipped' => [[
                $customer, 'shipped', 'Your order has shipped', "{$name} is on its way".(($event->data['tracking_number'] ?? null) ? " (tracking {$event->data['tracking_number']})." : '.'), "/dashboard/orders/{$order->id}",
            ]],
            'shipment_out_for_delivery' => [[
                $customer, 'shipment_out_for_delivery', 'Out for delivery', "{$name} is out for delivery today. Give the courier this code when you receive it: ".\App\Models\Shipment::where('order_id', $order->id)->latest('id')->value('delivery_code').'.', "/dashboard/orders/{$order->id}",
            ]],
            'shipment_delivered' => [[
                $customer, 'shipment_delivered', 'Delivered', "{$name} was delivered.", "/dashboard/orders/{$order->id}",
            ]],
            'shipment_failed' => [[
                $customer, 'shipment_failed', 'Delivery problem', "The carrier could not deliver {$name}. Choose a new delivery day from your order.", "/dashboard/orders/{$order->id}",
            ]],
            'delivery_rescheduled' => [[
                $this->sellerStaff($order), 'delivery_rescheduled', 'New delivery day', "The customer asked for {$name} to be delivered on ".($event->data['date'] ?? '').'.', "/dashboard/seller-orders/{$order->id}",
            ]],
            'return_approved' => [[
                $customer, 'return_approved', 'Return approved', "We approved the return of {$name}. Give the code ".\App\Models\OrderRefundRequest::where('order_id', $order->id)->latest('id')->value('return_code')." to whoever collects the parcel.", "/dashboard/orders/{$order->id}",
            ]],
            'return_collected' => [[
                $customer, 'return_collected', 'Return collected', "We collected {$name} and will inspect it shortly. Your refund follows once it passes.", "/dashboard/orders/{$order->id}",
            ]],
            'return_failed' => [[
                $customer, 'return_failed', 'Return did not pass inspection', "{$name} did not pass the return inspection: ".($event->data['note'] ?? '').'.', "/dashboard/orders/{$order->id}",
            ]],
            'cancelled' => [[
                $this->partnerStaff($this->askedPartnerIds($order), ['partner-manager']),
                'cancelled', 'Order cancelled', "Order #{$order->id} ({$name}) was cancelled.", '/dashboard/requests',
            ]],
            default => [],
        };
    }

    /** Skips whoever caused the event (nobody needs to be told what they just did), except receipts. */
    private function send(Collection $users, int $orderId, string $kind, string $title, string $body, string $path, array $vars, ?int $actorId): void
    {
        $skipActor = $kind !== 'payment_received';
        $arabic = ArabicMessages::render($kind, $vars);

        foreach ($users->reject(fn (User $u) => ($skipActor && $u->id === $actorId) || ! $u->is_active)->unique('id') as $user) {
            try {
                // Someone whose language is Arabic hears it in Arabic (unless an administrator published their own wording, which stays as written).
                [$t, $b] = $user->locale === 'ar' && $arabic && ! $this->templates->has($kind) ? $arabic : [$title, $body];
                $user->notify(new OrderUpdate($orderId, $kind, $t, $b, $path));
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    /** The people at the selling company, for an order of a seller's own product. */
    private function sellerStaff(Order $order): Collection
    {
        $sellerId = $order->product?->seller_id;

        return $sellerId ? User::role('seller')->where('company_id', $sellerId)->get() : collect();
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
