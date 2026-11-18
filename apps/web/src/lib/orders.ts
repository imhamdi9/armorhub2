import type { OrderEvent } from "@/types/platform";
import { formatDateTime, tr } from "@/lib/i18n";

export const STATUS_LABELS: Record<string, string> = {
  pending_quotes: "Waiting for quotes",
  quotes_received: "Quotes received",
  partner_selected: "Partner selected",
  awaiting_payment: "Awaiting payment",
  paid: "Paid",
  appointment_scheduled: "Appointment scheduled",
  service_in_progress: "In progress",
  completed: "Completed",
  cancelled: "Cancelled",
};

export const statusLabel = (status: string) =>
  tr(STATUS_LABELS[status] ?? status.replace(/_/g, " "));

const EVENT_LABELS: Record<string, string> = {
  created: "Order placed",
  quote_submitted: "A partner sent a quote",
  quote_accepted: "Quote accepted",
  paid: "Payment received",
  scheduled: "Appointment scheduled",
  started: "Work started",
  completed: "Work completed",
  cancelled: "Order cancelled",
  shipped: "Order shipped",
  shipment_out_for_delivery: "Out for delivery",
  shipment_delivered: "Delivered",
  shipment_failed: "Delivery failed",
  shipment_returned: "Returned to sender",
  auto_cancelled: "Cancelled because the seller did not ship in time",
  delivery_rescheduled: "Delivery day changed",
  partially_refunded: "Part of the payment is being refunded",
  partial_refund_sent: "Part of the payment was sent back",
  refund_requested: "Refund requested",
  refund_rejected: "Refund request declined",
  refunded: "Refund approved",
  refund_sent: "Refund sent",
  return_approved: "Return approved",
  return_collected: "Return collected",
  return_failed: "Return failed inspection",
};

export function eventLabel(event: OrderEvent): string {
  const when = event.data?.scheduled_at;

  if (event.type === "scheduled" && typeof when === "string") {
    return tr("Appointment scheduled for {when}", { when: formatDateTime(when) });
  }

  return tr(EVENT_LABELS[event.type] ?? event.type.replace(/_/g, " "));
}
