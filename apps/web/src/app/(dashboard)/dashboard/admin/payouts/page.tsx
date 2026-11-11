"use client";

import { useEffect, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { errorMessage } from "@/lib/errors";
import { formatDateTime, formatMoney, tr } from "@/lib/i18n";
import { createPayout, getPayouts, markPayoutPaid, type Balances, type Payout } from "@/services/admin.service";

/** Pay sellers out. Money moves by bank transfer outside the platform; here you gather what is owed and record the reference. */
export default function PayoutsPage() {
  const [sellers, setSellers] = useState<{ id: number; name: string; balances: Balances }[] | null>(null);
  const [payouts, setPayouts] = useState<Payout[]>([]);
  const [refs, setRefs] = useState<Record<number, string>>({});
  const [forbidden, setForbidden] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [tick, setTick] = useState(0);

  useEffect(() => {
    getPayouts().then((r) => { setSellers(r.sellers); setPayouts(r.payouts); }).catch(() => setForbidden(true));
  }, [tick]);

  async function run(action: () => Promise<unknown>) {
    setError(null);
    try {
      await action();
      setTick((t) => t + 1);
    } catch (e) {
      setError(errorMessage(e));
    }
  }

  if (forbidden) return <EmptyState>Only platform admins can open this page.</EmptyState>;
  if (!sellers) return <p className="text-muted-foreground">{tr("Loading...")}</p>;

  return (
    <div className="max-w-4xl">
      <PageHeader title="Payouts" />
      <p className="mb-4 text-sm text-muted-foreground">{tr("Sellers are owed their sales minus the platform commission, once the customer has received the goods. Prepare a payout, transfer the money by bank, then record the reference.")}</p>
      {error && <p className="mb-4 text-sm text-red-500" role="alert">{error}</p>}

      {sellers.length === 0 ? <EmptyState>{tr("No seller sales yet.")}</EmptyState> : (
        <div className="space-y-3">
          {sellers.map((s) => (
            <article key={s.id} className="rounded-xl border p-5">
              <h2 className="text-lg font-bold">{s.name}</h2>
              {Object.entries(s.balances).map(([currency, b]) => (
                <div key={currency} className="mt-2 flex flex-wrap items-center justify-between gap-3 text-sm">
                  <span>{tr("Held")}: {formatMoney(b.held, currency)} · {tr("Available")}: <strong>{formatMoney(b.available, currency)}</strong> · {tr("In a payout")}: {formatMoney(b.in_payout, currency)} · {tr("Paid")}: {formatMoney(b.paid, currency)}</span>
                  <Button size="sm" disabled={b.available <= 0} onClick={() => run(() => createPayout(s.id, currency))} aria-label={`${tr("Prepare payout")} ${s.name} ${currency}`}>{tr("Prepare payout")}</Button>
                </div>
              ))}
            </article>
          ))}
        </div>
      )}

      <h2 className="mb-3 mt-8 text-xl font-semibold">{tr("Payouts")}</h2>
      {payouts.length === 0 ? <EmptyState>{tr("No payouts yet.")}</EmptyState> : (
        <div className="divide-y rounded-xl border text-sm">
          {payouts.map((p) => (
            <div key={p.id} className="flex flex-wrap items-center justify-between gap-3 p-3" data-payout={p.id}>
              <span><strong>{p.company?.name}</strong> · {formatMoney(p.amount, p.currency)} · {formatDateTime(p.created_at)}</span>
              {p.status === "paid" ? (
                <span className="flex items-center gap-2"><Badge>{tr("Paid")}</Badge><span className="text-muted-foreground">{p.reference}</span></span>
              ) : (
                <span className="flex items-center gap-2">
                  <Input className="w-48" placeholder={tr("Bank reference")} aria-label={tr("Bank reference")} value={refs[p.id] ?? ""} onChange={(e) => setRefs((r) => ({ ...r, [p.id]: e.target.value }))} />
                  <Button size="sm" disabled={!refs[p.id]} onClick={() => run(() => markPayoutPaid(p.id, refs[p.id]))}>{tr("Mark paid")}</Button>
                </span>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
