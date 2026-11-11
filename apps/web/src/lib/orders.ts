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
};

export function eventLabel(event: OrderEvent): string {
  const when = event.data?.scheduled_at;

  if (event.type === "scheduled" && typeof when === "string") {
    return tr("Appointment scheduled for {when}", { when: formatDateTime(when) });
  }

  return tr(EVENT_LABELS[event.type] ?? event.type.replace(/_/g, " "));
}
