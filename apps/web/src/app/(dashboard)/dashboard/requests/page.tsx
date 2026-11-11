"use client";

import { useEffect, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { errorMessage } from "@/lib/errors";
import { getPartnerRequests, submitQuote } from "@/services/platform.service";
import type { PartnerQuote } from "@/types/platform";
import { formatMoney, tr } from "@/lib/i18n";

/** Service requests sent to the signed-in partner, to be priced. */
export default function RequestsPage() {
  const [quotes, setQuotes] = useState<PartnerQuote[] | null>(null);
  const [prices, setPrices] = useState<Record<number, string>>({});
  const [error, setError] = useState<string | null>(null);

  const [tick, setTick] = useState(0);

  useEffect(() => {
    getPartnerRequests()
      .then(setQuotes)
      .catch((e) => {
        setError(errorMessage(e));
        setQuotes([]);
      });
  }, [tick]);

  async function send(q: PartnerQuote) {
    setError(null);
    try {
      await submitQuote(q.id, Number(prices[q.id]));
      setTick((t) => t + 1);
    } catch (e) {
      setError(errorMessage(e));
    }
  }

  return (
    <div>
      <PageHeader title={tr("Requests")} />
      {error && <p className="mb-4 text-sm text-red-500">{error}</p>}

      {quotes === null ? (
        <p className="text-muted-foreground">{tr("Loading...")}</p>
      ) : quotes.length === 0 ? (
        <EmptyState>{tr("No requests right now.")}</EmptyState>
      ) : (
        <div className="space-y-3">
          {quotes.map((q) => (
            <div key={q.id} className="flex items-center justify-between rounded-lg border p-4">
              <div>
                <p className="font-semibold">
                  {q.order?.product?.name ?? tr("Order #{id}", { id: q.order_id })}
                </p>
                <p className="text-sm text-muted-foreground">
                  {tr("Order #{id}", { id: q.order_id })} · {tr("Product")} {q.order ? formatMoney(q.order.product_amount, q.order.currency) : ""}
                </p>
              </div>
              <div className="flex items-center gap-2">
                {q.status === "submitted" && <Badge variant="secondary">{tr("Quoted {price}", { price: String(q.price) })}</Badge>}
                <Input
                  className="w-28"
                  type="number"
                  min="0"
                  step="0.01"
                  placeholder={tr("Price")}
                  value={prices[q.id] ?? ""}
                  onChange={(e) => setPrices((p) => ({ ...p, [q.id]: e.target.value }))}
                />
                <Button size="sm" disabled={!prices[q.id]} onClick={() => send(q)}>
                  {q.status === "submitted" ? "Update" : "Send quote"}
                </Button>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
