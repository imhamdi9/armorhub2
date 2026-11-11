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
}