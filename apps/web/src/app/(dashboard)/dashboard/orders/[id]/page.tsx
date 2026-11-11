"use client";

import { useParams } from "next/navigation";
import { useEffect, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { errorMessage } from "@/lib/errors";
import { eventLabel, statusLabel } from "@/lib/orders";
import {
  acceptQuote,
  cancelOrder,
  getOrder,
  getOrderQuotes,
  payOrder,
} from "@/services/platform.service";
import type { Order, PartnerQuote } from "@/types/platform";

export default function OrderDetailPage() {
  const { id } = useParams<{ id: string }>();
  const [order, setOrder] = useState<Order | null>(null);
  const [quotes, setQuotes] = useState<PartnerQuote[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [tick, setTick] = useState(0);

  useEffect(() => {
    getOrder(id).then(setOrder);
    getOrderQuotes(id).then(setQuotes).catch(() => setQuotes([]));
  }, [id, tick]);

  async function run(action: () => Promise<unknown>) {
    setError(null);
    try {
      await action();
      setTick((t) => t + 1);
    } catch (e) {
      setError(errorMessage(e));
    }
  }

  if (!order) return <p className="text-muted-foreground">Loading...</p>;

  const submitted = quotes.filter((q) => q.status !== "pending");
  const canChoose = ["pending_quotes", "quotes_received"].includes(order.status);
  const canPay = ["partner_selected", "awaiting_payment"].includes(order.status);
  const canCancel = ["pending_quotes", "quotes_received", "partner_selected", "awaiting_payment"].includes(order.status);

  return (
    <div className="max-w-2xl">
      <PageHeader
        title={`Order #${order.id}`}
        action={<Badge variant="secondary">{statusLabel(order.status)}</Badge>}
      />

      <div className="mb-6 rounded-lg border p-4 text-sm">
        <p className="font-semibold">{order.product?.name}</p>
        <p>Product: {order.product_amount} {order.currency}</p>
        <p>Service: {order.service_amount} {order.currency}</p>
        <p className="mt-1 text-base font-bold">Total: {order.total_amount} {order.currency}</p>
        {order.scheduled_at && order.status !== "cancelled" && (
          <p className="mt-2">Appointment: {new Date(order.scheduled_at).toLocaleString()}</p>
        )}
      </div>

      {order.warranty && (
        <div className="mb-6 rounded-lg border border-green-300 bg-green-50 p-4 text-sm">
          <p className="font-semibold">Warranty</p>
          <p>
            Valid until {new Date(order.warranty.ends_at).toLocaleDateString()}
          </p>
        </div>
      )}

      {error && <p className="mb-4 text-sm text-red-500">{error}</p>}

      {order.status !== "cancelled" && (
        <>
          <h2 className="mb-3 text-xl font-semibold">Partner quotes</h2>
          {submitted.length === 0 ? (
            <EmptyState>Waiting for partners to respond ({quotes.length} asked).</EmptyState>
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
                      <Badge>Selected</Badge>
                    ) : q.status === "rejected" ? (
                      <Badge variant="outline">Declined</Badge>
                    ) : (
                      canChoose && <Button size="sm" onClick={() => run(() => acceptQuote(q.id))}>Accept</Button>
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
          <Button onClick={() => run(() => payOrder(order.id, order.total_amount))}>
            Pay {order.total_amount} {order.currency}
          </Button>
        )}
        {canCancel && (
          <Button variant="outline" onClick={() => run(() => cancelOrder(order.id))}>
            Cancel order
          </Button>
        )}
      </div>

      {order.events && order.events.length > 0 && (
        <>
          <h2 className="mb-3 mt-8 text-xl font-semibold">Timeline</h2>
          <ol className="space-y-3 border-l pl-4">
            {order.events.map((ev) => (
              <li key={ev.id}>
                <p className="font-medium">{eventLabel(ev)}</p>
                <p className="text-xs text-muted-foreground">
                  {new Date(ev.created_at).toLocaleString()}
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
