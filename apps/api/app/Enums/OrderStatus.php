<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PendingQuotes = 'pending_quotes';
    case QuotesReceived = 'quotes_received';
    case PartnerSelected = 'partner_selected';
    case AwaitingPayment = 'awaiting_payment';
    case Paid = 'paid';
    case AppointmentScheduled = 'appointment_scheduled';
    case ServiceInProgress = 'service_in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /** Statuses in which a customer may still pick a partner's quote. */
    public function isOpenForQuotes(): bool
    {
        return in_array($this, [self::PendingQuotes, self::QuotesReceived], true);
    }

    /** Before payment, an order can still be cancelled. */
    public function isCancellable(): bool
    {
        return in_array($this, [self::PendingQuotes, self::QuotesReceived, self::PartnerSelected, self::AwaitingPayment], true);
    }

    /** Statuses in which the assigned partner is working the job. */
    public function isJob(): bool
    {
        return in_array($this, [self::Paid, self::AppointmentScheduled, self::ServiceInProgress, self::Completed], true);
    }
}
