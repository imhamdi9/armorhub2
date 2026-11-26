"use client";

import { useEffect, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { errorMessage } from "@/lib/errors";
import { formatDateTime, formatMoney, tr } from "@/lib/i18n";
import { createPayout, decidePayoutAccount, getPayoutAccounts, getPayouts, markPayoutPaid, type Balances, type Payout, type PayoutAccountRow } from "@/services/admin.service";
import { LoadingBlock } from "@/components/ui/loading-block";

/** Pay sellers out. Money moves by bank transfer outside the platform; here you gather what is owed and record the reference. */
export default function PayoutsPage() {
  const [sellers, setSellers] = useState<{ id: number; name: string; balances: Balances }[] | null>(null);
  const [payouts, setPayouts] = useState<Payout[]>([]);
  const [accounts, setAccounts] = useState<PayoutAccountRow[]>([]);
  const [refs, setRefs] = useState<Record<number, string>>({});
  const [forbidden, setForbidden] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [tick, setTick] = useState(0);

  useEffect(() => {
    getPayouts().then((r) => { setSellers(r.sellers); setPayouts(r.payouts); }).catch(() => setForbidden(true));
    getPayoutAccounts().then(setAccounts).catch(() => setAccounts([]));
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
  if (!sellers) return <LoadingBlock />;

  return (
    <div className="max-w-4xl">
      <PageHeader title="Payouts" />
      <p className="mb-4 text-sm text-muted-foreground">{tr("Sellers are owed their sales minus the platform commission, once the customer has received the goods. Prepare a payout, transfer the money by bank, then record the reference.")}</p>
      {error && <p className="mb-4 text-sm text-red-500" role="alert">{error}</p>}

      <h2 className="mb-3 mt-2 text-xl font-semibold">{tr("Payout accounts")}</h2>
      {accounts.length === 0 ? <EmptyState>{tr("No payout accounts yet.")}</EmptyState> : (
        <div className="mb-8 divide-y rounded-xl border text-sm" data-testid="payout-accounts">
          {accounts.map((a) => (
            <div key={a.id} className="flex flex-wrap items-center justify-between gap-3 p-3">
              <span><strong>{a.company}</strong> · {a.method === "bank" ? `${a.bank_name} · ` : ""}<span dir="ltr">{a.number}</span> · {a.holder}</span>
              {a.status === "pending" ? (
                <span className="flex gap-2">
                  <Button size="sm" onClick={() => run(() => decidePayoutAccount(a.id, "verified"))}>{tr("Verify")}</Button>
                  <Button size="sm" variant="outline" onClick={() => { const n = window.prompt(tr("Why is it not accepted?")); if (n) run(() => decidePayoutAccount(a.id, "rejected", n)); }}>{tr("Reject")}</Button>
                </span>
              ) : <Badge variant={a.status === "verified" ? "default" : "secondary"}>{tr(a.status === "verified" ? "Verified" : "Rejected")}</Badge>}
            </div>
          ))}
        </div>
      )}

      {sellers.length === 0 ? <EmptyState>{tr("No seller sales yet.")}</EmptyState> : (
        <div className="space-y-3">
          {sellers.map((s) => (
            <article key={s.id} className="rounded-xl border p-5">
              <h2 className="text-lg font-bold">{s.name}</h2>
              {Object.entries(s.balances).map(([currency, b]) => (
                <div key={currency} className="mt-2 flex flex-wrap items-center justify-between gap-3 text-sm">
                  <span>{tr("Held")}: {formatMoney(b.held, currency)} · {tr("Available")}: <strong>{formatMoney(b.available, currency)}</strong> · {tr("In a payout")}: {formatMoney(b.in_payout, currency)} · {tr("Paid")}: {formatMoney(b.paid, currency)}</span>
                  <Button size="sm" disabled={b.available <= 0} onClick={() => run(() => createPayout(s.id, currency))} aria-label={`${tr("Prepare payout")} ${s.name} ${currency}`}>{tr("Prepare payout")}</Button>
                  {b.available < 0 && (
                    <p className="basis-full rounded-lg border border-red-300 bg-red-50 p-3 text-red-900" role="alert" data-testid="negative-balance">
                      {tr("This seller owes {amount}: money was refunded to customers after it had been paid out. Nothing can be paid out until the balance is settled; it is taken from the seller's next sales automatically.", { amount: formatMoney(Math.abs(b.available), currency) })}
                    </p>
                  )}
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
              <span><strong>{p.company?.name}</strong> · {formatMoney(p.amount, p.currency)} · {formatDateTime(p.created_at)}{p.account ? <> · <span dir="ltr" className="text-muted-foreground">{p.account.bank_name ? `${p.account.bank_name} ` : ""}{p.account.number} ({p.account.holder})</span></> : null}</span>
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
