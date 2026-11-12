import type { DashboardStats } from "@/services/platform.service";
import type { User } from "@/types/auth";

export interface NextStep { id: string; title: string; text: string; href: string }

const card = (stats: DashboardStats, key: string) => stats.cards.find((c) => c.key === key)?.value ?? 0;
const status = (stats: DashboardStats, key: string) => stats.orders_by_status[key] ?? 0;
const has = (user: User, ...roles: string[]) => !!user.roles?.some((r) => roles.includes(r));

/**
 * What this person should do next, from their role and what is waiting for them. Returns an empty list when there is nothing to do,
 * so the dashboard can say "all caught up" instead of leaving them to work it out from a grid of numbers.
 * Titles use {n}; the screen fills it in and translates.
 */
export function nextSteps(user: User, stats: DashboardStats): NextStep[] {
  const steps: NextStep[] = [];

  if (has(user, "platform-admin")) {
    steps.push({ id: "admin", title: "Review what needs attention", text: "Applications, security checks and failed deliveries are gathered in one place.", href: "/dashboard/admin" });

    return steps;
  }

  if (has(user, "seller")) {
    if (card(stats, "products") === 0) steps.push({ id: "first-product", title: "Add your first product", text: "Customers can order as soon as it has a name, a price and a photo.", href: "/dashboard/products" });
    const toShip = card(stats, "to_ship");
    if (toShip > 0) steps.push({ id: "ship", title: "{n} paid orders are waiting to be shipped", text: "Ship them and add the tracking number so customers can follow along.", href: "/dashboard/seller-orders", ...{ n: toShip } } as NextStep);

    return steps;
  }

  if (has(user, "partner-manager", "technician")) {
    const requests = card(stats, "open_requests");
    const toSchedule = card(stats, "to_schedule");
    if (has(user, "partner-manager") && requests > 0) steps.push({ id: "quotes", title: "{n} quote requests are waiting for your price", text: "The first good quote often wins the job.", href: "/dashboard/requests", ...{ n: requests } } as NextStep);
    if (toSchedule > 0) steps.push({ id: "schedule", title: "{n} paid jobs need an appointment", text: "Pick a time so the customer knows when to expect you.", href: "/dashboard/jobs", ...{ n: toSchedule } } as NextStep);
    if (has(user, "partner-manager") && (user.latitude == null || user.latitude === undefined)) steps.push({ id: "location", title: "Set your location", text: "Customers near you can only be matched with your business once it is set.", href: "/dashboard/partners" });

    return steps;
  }

  if (has(user, "company-owner")) {
    const open = card(stats, "open");
    if (open > 0) steps.push({ id: "open", title: "{n} orders are waiting on quotes or payment", text: "See which ones need a nudge.", href: "/dashboard/orders", ...{ n: open } } as NextStep);
    if (card(stats, "team") <= 1) steps.push({ id: "team", title: "Invite your team", text: "Add managers and technicians so work is shared from day one.", href: "/dashboard/users" });
    steps.push({ id: "storefront", title: "Check how your store looks to customers", text: "Your headline, colours and hero picture are what visitors see first.", href: "/dashboard/storefront" });

    return steps;
  }

  // A customer.
  if (card(stats, "orders") === 0) steps.push({ id: "first-order", title: "Place your first order", text: "Choose what you need and get prices from trusted professionals.", href: "/dashboard/orders/new" });
  const quotes = status(stats, "quotes_received");
  if (quotes > 0) steps.push({ id: "quotes", title: "{n} orders have quotes to compare", text: "Pick the one you like. You pay only after you accept.", href: "/dashboard/orders?status=quotes_received", ...{ n: quotes } } as NextStep);
  const pay = status(stats, "partner_selected") + status(stats, "awaiting_payment");
  if (pay > 0) steps.push({ id: "pay", title: "{n} orders are ready for payment", text: "Pay securely to confirm the booking.", href: "/dashboard/orders", ...{ n: pay } } as NextStep);

  return steps;
}

/** Plain-English "what happens next" for an order, for the person looking at it. */
export function statusGuide(statusKey: string, forCustomer: boolean): string | null {
  const customer: Record<string, string> = {
    pending_quotes: "Nearby professionals are preparing their prices. You will get a notification when the first quote arrives, usually within a few hours.",
    quotes_received: "Quotes are in. Compare them and accept the one you like. You only pay after you accept.",
    partner_selected: "Great choice. The last step is to pay securely, and your booking is confirmed.",
    awaiting_payment: "Pay securely to confirm your booking. Your card details never reach us.",
    paid: "Payment received. The professional will now set an appointment, and you will be notified.",
    appointment_scheduled: "Your appointment is set. You can see the date in the timeline below.",
    service_in_progress: "The work has started. You will be told as soon as it is done.",
    completed: "All done. Your warranty, if the product has one, is now active under Warranties.",
    cancelled: "This order was cancelled. You can place a new one any time.",
  };
  const staff: Record<string, string> = {
    pending_quotes: "Waiting for partners to send their prices.",
    quotes_received: "The customer is comparing quotes.",
    partner_selected: "A quote was accepted. Waiting for the customer to pay.",
    awaiting_payment: "Waiting for the customer to pay.",
    paid: "Paid. A technician should schedule the appointment.",
    appointment_scheduled: "Scheduled. The job can be started on the day.",
    service_in_progress: "The work is under way. Mark it complete when it is done.",
    completed: "Completed. The warranty has started.",
    cancelled: "Cancelled.",
  };

  return (forCustomer ? customer : staff)[statusKey] ?? null;
}
