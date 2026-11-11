<?php

namespace App\Products\Armorhub\Workflows;

/**
 * The life of an ArmorHub order, as data. Changing how orders move (a market that allows cancelling after payment, an extra
 * approval step) is a change to this definition or to a tenant's override of it, not to the order code.
 */
final class OrderWorkflow
{
    public const KEY = \App\Models\Order::WORKFLOW;

    public static function definition(): array
    {
        $open = ['pending_quotes', 'quotes_received', 'partner_selected', 'awaiting_payment'];

        return [
            'key' => self::KEY,
            'version' => 1,
            'initial' => 'pending_quotes',
            'states' => [
                'pending_quotes' => ['label' => 'Waiting for quotes'],
                'quotes_received' => ['label' => 'Quotes received'],
                'partner_selected' => ['label' => 'Partner selected'],
                'awaiting_payment' => ['label' => 'Awaiting payment'],
                'paid' => ['label' => 'Paid'],
                'appointment_scheduled' => ['label' => 'Appointment scheduled'],
                'service_in_progress' => ['label' => 'In progress'],
                'completed' => ['label' => 'Completed', 'final' => true],
                'cancelled' => ['label' => 'Cancelled', 'final' => true],
            ],
            'transitions' => [
                'accept_quote' => ['from' => ['pending_quotes', 'quotes_received'], 'to' => 'partner_selected', 'event' => 'quote_accepted',
                    'message' => 'A quote has already been selected for this order.'],
                // Payment is recorded by the payment system whatever state the order is in (money received must never be refused),
                // so this transition documents the normal path but is not enforced on the payment webhook.
                'pay' => ['from' => ['partner_selected', 'awaiting_payment'], 'to' => 'paid', 'event' => 'paid'],
                'schedule' => ['from' => ['paid', 'appointment_scheduled'], 'to' => 'appointment_scheduled', 'event' => 'scheduled'],
                'start' => ['from' => ['appointment_scheduled'], 'to' => 'service_in_progress', 'event' => 'started'],
                'complete' => ['from' => ['service_in_progress'], 'to' => 'completed', 'event' => 'completed', 'actions' => ['armorhub.grant_warranty']],
                'cancel' => ['from' => $open, 'to' => 'cancelled', 'event' => 'cancelled', 'actions' => ['armorhub.reject_open_quotes'],
                    'message' => 'Paid orders cannot be cancelled here; please contact support.'],
            ],
        ];
    }
}
