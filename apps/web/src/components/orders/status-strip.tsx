"use client";

import { tr } from "@/lib/i18n";

export interface OrderStatuses {
  order: string;
  payment: string;
  product?: string | null;
  service?: string | null;
  settlement?: string | null;
}

const WORDS: Record<string, Record<string, string>> = {
  order: { draft: "Being prepared", pending_payment: "Waiting for payment", confirmed: "Confirmed", completed: "Completed", cancelled: "Cancelled" },
  payment: { pending: "Payment pending", cash_on_delivery: "Pay on delivery", paid: "Paid", failed: "Payment failed", refunded: "Refunded", not_required: "No payment" },
  product: { pending: "Goods: waiting", processing: "Goods: being prepared", ready: "Goods: ready to ship", shipped: "Goods: on their way", delivered: "Goods: delivered", returned: "Goods: returned", cancelled: "Goods: cancelled" },
  service: { requested: "Service: requested", quoted: "Service: quoted", accepted: "Service: accepted", booked: "Service: booked", in_progress: "Service: in progress", completed: "Service: done", cancelled: "Service: cancelled" },
  settlement: { pending: "Seller payment: held", eligible: "Seller payment: ready", processing: "Seller payment: being sent", paid: "Seller payment: sent" },
};

const GOOD = new Set(["confirmed", "completed", "paid", "delivered", "booked", "eligible"]);
const BAD = new Set(["cancelled", "failed", "returned"]);

/** The parts of an order each have their own status: the order, the payment, the goods, the service (and, for staff, the settlement). */
export function OrderStatusStrip({ statuses }: { statuses?: OrderStatuses | null }) {
  if (!statuses) return null;
  const parts = (["order", "payment", "product", "service", "settlement"] as const)
    .map((k) => [k, statuses[k]] as const)
    .filter((p): p is [typeof p[0], string] => !!p[1]);

  return (
    <ul className="flex flex-wrap gap-2" data-testid="order-statuses" aria-label={tr("Order status")}>
      {parts.map(([part, value]) => (
        <li key={part} data-testid={`status-${part}`} className={`rounded-full border px-3 py-1 text-xs font-semibold ${BAD.has(value) ? "border-red-200 bg-red-50 text-red-700" : GOOD.has(value) ? "border-emerald-200 bg-emerald-50 text-emerald-800" : "bg-muted text-foreground"}`}>
          {tr(WORDS[part]?.[value] ?? value.replace(/_/g, " "))}
        </li>
      ))}
    </ul>
  );
}
