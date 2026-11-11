"use client";

import { useParams } from "next/navigation";
import { useEffect, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { errorMessage } from "@/lib/errors";
import {
  acceptQuote,
  getOrder,
  getOrderQuotes,
  payOrder,
} from "@/services/platform.service";
import type { Order, PartnerQuote, Product } from "@/types/platform";

export default function OrderDetailPage() {
  const { id } = useParams<{ id: string }>();
  const [order, setOrder] = useState<(Order & { product?: Product }) | null>(null);
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

  return (
    <div className="max-w-2xl">
      <PageHeader title={`Order #${order.id}`} action={<Badge variant="secondary">{order.status.replace(/_/g, " ")}</Badge>} />

      <div className="mb-6 rounded-lg border p-4 text-sm">
        <p className="font-semibold">{order.product?.name}</p>
        <p>Product: {order.product_amount} {order.currency}</p>
        <p>Service: {order.service_amount} {order.currency}</p>
        <p className="mt-1 text-base font-bold">Total: {order.total_amount} {order.currency}</p>
      </div>

      {error && <p className="mb-4 text-sm text-red-500">{error}</p>}

      <h2 className="mb-3 text-xl font-semibold">Partner quotes</h2>
      {submitted.length === 0 ? (
        <EmptyState>
          Waiting for partners to respond ({quotes.length} asked).
        </EmptyState>
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

      {canPay && (
        <Button className="mt-6" onClick={() => run(() => payOrder(order.id, order.total_amount))}>
          Pay {order.total_amount} {order.currency}
        </Button>
      )}
    </div>
  );
}
