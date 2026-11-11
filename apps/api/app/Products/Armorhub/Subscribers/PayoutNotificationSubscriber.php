<?php

namespace App\Products\Armorhub\Subscribers;

use App\Core\Events\EventEnvelope;
use App\Core\Events\EventSubscriber;
use App\Models\User;
use App\Notifications\PayoutPaid;

/** Tells a seller's staff when a payout has been sent. */
class PayoutNotificationSubscriber implements EventSubscriber
{
    public function id(): string
    {
        return 'armorhub.payout-notifications';
    }

    public function handles(): array
    {
        return ['payout.paid'];
    }

    public function handle(EventEnvelope $event): void
    {
        $amount = number_format((float) $event->payload['amount'], 2).' '.$event->payload['currency'];

        foreach (User::role('seller')->where('company_id', $event->payload['company_id'])->where('is_active', true)->get() as $user) {
            $user->notify(new PayoutPaid($amount, (string) ($event->payload['reference'] ?? '')));
        }
    }
}
