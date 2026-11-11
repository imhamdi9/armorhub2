"use client";

import { useParams, useSearchParams } from "next/navigation";
import { Suspense, useEffect, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { errorMessage } from "@/lib/errors";
import { ShippingPanel } from "@/components/orders/shipping-panel";
import { eventLabel, statusLabel } from "@/lib/orders";
import {
  acceptQuote,
  cancelOrder,
  getOrder,
  getOrderQuotes,
  payOrder,
} from "@/services/platform.service";
import type { Order, PartnerQuote } from "@/types/platform";
import { formatDate, formatDateTime, formatMoney, tr } from "@/lib/i18n";

export default function OrderDetailPage() {
  return (
    <Suspense>
      <OrderDetail />
    </Suspense>
  );
}

const MAX_CONFIRM_POLLS = 20;

function OrderDetail() {
  const { id } = useParams<{ id: string }>();
  const checkout = useSearchParams().get("checkout");
  const [order, setOrder] = useState<Order | null>(null);
  const [quotes, setQuotes] = useState<PartnerQuote[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [tick, setTick] = useState(0);
  const [polls, setPolls] = useState(0);

  useEffect(() => {
    getOrder(id).then(setOrder);
    getOrderQuotes(id).then(setQuotes).catch(() => setQuotes([]));
  }, [id, tick]);

  // Back from the payment page: the webhook, not the browser, marks the order paid, so wait for it.
  const confirming = checkout === "success" && !!order && order.status !== "paid" && polls < MAX_CONFIRM_POLLS && order.status === "partner_selected";

  useEffect(() => {
    if (!confirming) return;
    const timer = setTimeout(() => {
      setPolls((n) => n + 1);
      setTick((t) => t + 1);
    }, 3000);
    return () => clearTimeout(timer);
  }, [confirming, polls]);

  async function pay() {
    if (!order) return;
    setError(null);
    try {
      const res = await payOrder(order.id, order.total_amount);
      if (res.checkout_url) {
        window.location.href = res.checkout_url;
        return;
      }
      setTick((t) => t + 1);
    } catch (e) {
      setError(errorMessage(e));
    }
  }

  async function run(action: () => Promise<unknown>) {
    setError(null);
    try {
      await action();
      setTick((t) => t + 1);
    } catch (e) {
      setError(errorMessage(e));
    }
  }

  if (!order) return <p className="text-muted-foreground">{tr("Loading...")}</p>;

  const submitted = quotes.filter((q) => q.status !== "pending");
  const canChoose = ["pending_quotes", "quotes_received"].includes(order.status);
  const canPay = ["partner_selected", "awaiting_payment"].includes(order.status);
  const canCancel = ["pending_quotes", "quotes_received", "partner_selected", "awaiting_payment"].includes(order.status);

  return (
    <div className="max-w-2xl">
      <PageHeader
        title={tr("Order #{id}", { id: order.id })}
        action={<Badge variant="secondary">{statusLabel(order.status)}</Badge>}
      />

      <div className="mb-6 rounded-lg border p-4 text-sm">
        <p className="font-semibold">{order.product?.name}</p>
        <p>{tr("Product")}: {formatMoney(order.product_amount, order.currency)}</p>
        <p>{tr("Service")}: {formatMoney(order.service_amount, order.currency)}</p>
        <p className="mt-1 text-base font-bold">{tr("Total")}: {formatMoney(order.total_amount, order.currency)}</p>
        {Number(order.tax_rate) > 0 && (
          <p className="text-muted-foreground">
            {tr("Includes VAT ({rate}%)", { rate: Number(order.tax_rate) })}: {formatMoney(order.tax_amount ?? 0, order.currency)}
          </p>
        )}
        {order.scheduled_at && order.status !== "cancelled" && (
          <p className="mt-2">{tr("Appointment")}: {formatDateTime(order.scheduled_at)}</p>
        )}
      </div>

      <ShippingPanel orderId={order.id} paid={["paid", "appointment_scheduled", "service_in_progress", "completed"].includes(order.status)} />

      {order.warranty && (
        <div className="mb-6 rounded-lg border border-green-300 bg-green-50 p-4 text-sm">
          <p className="font-semibold">{tr("Warranty")}</p>
          <p>
            {tr("Valid until {date}", { date: formatDate(order.warranty.ends_at) })}
          </p>
        </div>
      )}

      {error && <p className="mb-4 text-sm text-red-500">{error}</p>}

      {confirming && (
        <p className="mb-4 rounded-lg border border-blue-300 bg-blue-50 p-3 text-sm">
          {tr("Confirming your payment… this usually takes a few seconds.")}
        </p>
      )}
      {checkout === "success" && order.status === "partner_selected" && polls >= MAX_CONFIRM_POLLS && (
        <p className="mb-4 rounded-lg border p-3 text-sm">
          We have not received the confirmation yet. If you were charged, it will appear here shortly; no need to pay again.
        </p>
      )}
      {checkout === "cancelled" && order.status === "partner_selected" && (
        <p className="mb-4 rounded-lg border p-3 text-sm">{tr("Payment was cancelled. You can try again below.")}</p>
      )}

      {order.status !== "cancelled" && (
        <>
          <h2 className="mb-3 text-xl font-semibold">{tr("Partner quotes")}</h2>
          {submitted.length === 0 ? (
            <EmptyState>{tr("Waiting for partners to respond ({n} asked).", { n: quotes.length })}</EmptyState>
          ) : (
            <div className="space-y-3">
              {submitted.map((q) => (
                <div key={q.id} className="flex items-center justify-between rounded-lg border p-4">
                  <div>
                    <p className="font-semibold">{q.partner?.name}</p>
                    <p className="text-sm text-muted-foreground">
                      {[q.partner?.city, q.partner?.country].filter(Boolean).join(", ")}
                    </p>
                  </div>
                  <div className="flex items-center gap-3">
                    <span className="font-semibold">{q.price}</span>
                    {q.status === "accepted" ? (
                      <Badge>{tr("Selected")}</Badge>
                    ) : q.status === "rejected" ? (
                      <Badge variant="outline">{tr("Declined")}</Badge>
                    ) : (
                      canChoose && <Button size="sm" onClick={() => run(() => acceptQuote(q.id))}>{tr("Accept")}</Button>
                    )}
                  </div>
                </div>
              ))}
            </div>
          )}
        </>
      )}

      <div className="mt-6 flex gap-3">
        {canPay && (
          <Button disabled={confirming} onClick={pay}>
            {tr("Pay {amount}", { amount: formatMoney(order.total_amount, order.currency) })}
          </Button>
        )}
        {canCancel && (
          <Button variant="outline" onClick={() => run(() => cancelOrder(order.id))}>
            {tr("Cancel order")}
          </Button>
        )}
      </div>

      {order.events && order.events.length > 0 && (
        <>
          <h2 className="mb-3 mt-8 text-xl font-semibold">{tr("Timeline")}</h2>
          <ol className="space-y-3 border-s ps-4">
            {order.events.map((ev) => (
              <li key={ev.id}>
                <p className="font-medium">{eventLabel(ev)}</p>
                <p className="text-xs text-muted-foreground">
                  {formatDateTime(ev.created_at)}
                  {ev.user ? ` · ${ev.user.name}` : ""}
                </p>
              </li>
            ))}
          </ol>
        </>
      )}
    </div>
  );
}
