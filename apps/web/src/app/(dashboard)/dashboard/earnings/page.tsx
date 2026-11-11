"use client";

import { useEffect, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { Badge } from "@/components/ui/badge";
import { formatDateTime, formatMoney, tr } from "@/lib/i18n";
import { getSellerEarnings } from "@/services/platform.service";

type Earnings = Awaited<ReturnType<typeof getSellerEarnings>>;

/** What the seller is owed: held until the buyer has the goods, available to be paid, and already paid. */
export default function EarningsPage() {
  const [data, setData] = useState<Earnings | null>(null);
  const [forbidden, setForbidden] = useState(false);

  useEffect(() => {
    getSellerEarnings().then(setData).catch(() => setForbidden(true));
  }, []);

  if (forbidden) return <EmptyState>{tr("You do not have access to earnings.")}</EmptyState>;
  if (!data) return <p className="text-muted-foreground">{tr("Loading...")}</p>;

  const currencies = Object.keys(data.balances);
  const cards: [keyof Earnings["balances"][string], string][] = [["held", "Held until delivery"], ["available", "Available to pay out"], ["in_payout", "In a payout"], ["paid", "Paid out"]];

  return (
    <div className="max-w-3xl">
      <PageHeader title={tr("Earnings")} />
      <p className="mb-6 text-sm text-muted-foreground">{tr("A sale's money is held until the customer receives the goods, then becomes available. The platform pays it out and records the bank reference here. Amounts are after the platform's commission.")}</p>

      {currencies.length === 0 ? (
        <EmptyState>{tr("No sales yet.")}</EmptyState>
      ) : (
        currencies.map((c) => (
          <div key={c} className="mb-6 grid grid-cols-2 gap-3 md:grid-cols-4">
            {cards.map(([key, label]) => (
              <div key={key} className="rounded-xl border p-4">
                <p className="text-xs text-muted-foreground">{tr(label)}</p>
                <p className="text-2xl font-black tracking-tight" data-testid={`balance-${key}`}>{formatMoney(data.balances[c][key], c)}</p>
              </div>
            ))}
          </div>
        ))
      )}

      {data.payouts.length > 0 && (
        <>
          <h2 className="mb-2 mt-6 text-lg font-semibold">{tr("Payouts")}</h2>
          <div className="divide-y rounded-xl border text-sm">
            {data.payouts.map((p) => (
              <div key={p.id} className="flex flex-wrap items-center justify-between gap-2 p-3">
                <span>{formatMoney(p.amount, p.currency)} {p.reference ? `· ${p.reference}` : ""}</span>
                <span className="flex items-center gap-2 text-muted-foreground">{formatDateTime(p.paid_at ?? p.created_at)}<Badge variant={p.status === "paid" ? "default" : "secondary"}>{tr(p.status === "paid" ? "Paid" : "Pending")}</Badge></span>
              </div>
            ))}
          </div>
        </>
      )}

      {data.entries.length > 0 && (
        <>
          <h2 className="mb-2 mt-6 text-lg font-semibold">{tr("Statement")}</h2>
          <div className="divide-y rounded-xl border text-sm">
            {data.entries.map((e) => (
              <div key={e.id} className="flex flex-wrap items-center justify-between gap-2 p-3">
                <span>{tr(e.type === "sale" ? "Sale" : "Platform commission")} · {tr("Order #{id}", { id: e.order_id })}</span>
                <span className="flex items-center gap-2"><strong>{formatMoney(e.amount, e.currency)}</strong><Badge variant="secondary">{tr(e.status === "held" ? "Held" : e.status === "available" ? "Available" : "Paid")}</Badge></span>
              </div>
            ))}
          </div>
        </>
      )}
    </div>
  );
}
