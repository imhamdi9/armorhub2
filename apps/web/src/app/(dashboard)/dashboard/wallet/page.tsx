"use client";

import { useEffect, useState } from "react";

import { field, Note } from "@/components/installations/shared";
import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { errorMessage } from "@/lib/errors";
import { formatDateTime, formatMoney, tr } from "@/lib/i18n";
import { getWallet, withdraw, type Wallet } from "@/services/installation.service";

/** What the centre has earned: held until the work is proven, available, and withdrawn by bank transfer or wallet. */
export default function WalletPage() {
  const [w, setW] = useState<Wallet | null>(null);
  const [forbidden, setForbidden] = useState(false);
  const [err, setErr] = useState<string | null>(null);
  const [ok, setOk] = useState(false);
  const [method, setMethod] = useState("bank");
  const load = () => getWallet().then(setW).catch(() => setForbidden(true));
  useEffect(() => { load(); }, []);

  if (forbidden) return <EmptyState>{tr("You do not have access to the wallet.")}</EmptyState>;
  if (!w) return <p className="text-muted-foreground">{tr("Loading...")}</p>;
  const currencies = Object.keys(w.balances);
  const cards: [keyof Wallet["balances"][string], string][] = [["held", "Held until the work is confirmed"], ["available", "Available to withdraw"], ["pending_withdrawal", "In a withdrawal"], ["withdrawn", "Withdrawn"]];

  async function submit(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault(); setErr(null); setOk(false);
    const f = Object.fromEntries(new FormData(e.currentTarget).entries());
    try { await withdraw({ ...f, amount: Number(f.amount), method }); setOk(true); (e.target as HTMLFormElement).reset(); await load(); } catch (x) { setErr(errorMessage(x)); }
  }

  return (
    <div className="max-w-3xl">
      <PageHeader title="Wallet" />
      <p className="mb-6 text-sm text-muted-foreground">{tr("A job's money is held until the work is proven and confirmed, then becomes available. Amounts are after the platform fee. Withdraw by bank transfer or wallet; the platform pays and records the reference.")}</p>

      {currencies.length === 0 ? <EmptyState>{tr("No completed jobs yet.")}</EmptyState> : currencies.map((c) => (
        <div key={c} className="mb-6 grid grid-cols-2 gap-3 md:grid-cols-4">
          {cards.map(([k, label]) => (
            <div key={k} className="rounded-xl border p-4"><p className="text-xs text-muted-foreground">{tr(label)}</p><p className="text-2xl font-black tracking-tight" data-testid={`wallet-${k}`}>{formatMoney(w.balances[c][k], c)}</p></div>
          ))}
        </div>
      ))}

      {currencies.length > 0 && (
        <form onSubmit={submit} className="mb-8 space-y-3 rounded-xl border p-4">
          <h2 className="text-lg font-semibold">{tr("Withdraw")}</h2>
          <input type="hidden" name="currency" value={currencies[0]} />
          <div><Label htmlFor="amount">{tr("Amount")} (≥ {w.min_withdrawal})</Label><Input id="amount" name="amount" type="number" min={w.min_withdrawal} step="0.01" required /></div>
          <div><Label htmlFor="method">{tr("Method")}</Label><select id="method" className={field} value={method} onChange={(e) => setMethod(e.target.value)}><option value="bank">{tr("Bank transfer")}</option><option value="wallet">{tr("Mobile wallet")}</option></select></div>
          <div><Label htmlFor="account_name">{tr("Account holder name")}</Label><Input id="account_name" name="account_name" required maxLength={120} /></div>
          <div><Label htmlFor="account_number">{method === "bank" ? tr("IBAN or account number") : tr("Wallet phone number")}</Label><Input id="account_number" name="account_number" required minLength={6} maxLength={60} dir="ltr" /></div>
          {method === "bank" && <div><Label htmlFor="bank_name">{tr("Bank name")}</Label><Input id="bank_name" name="bank_name" required maxLength={120} /></div>}
          {err && <p role="alert" className="text-sm text-red-600">{err}</p>}
          {ok && <Note tone="ok">{tr("Your withdrawal request was sent.")}</Note>}
          <Button type="submit">{tr("Request withdrawal")}</Button>
        </form>
      )}

      {w.withdrawals.length > 0 && (
        <>
          <h2 className="mb-2 text-lg font-semibold">{tr("Withdrawals")}</h2>
          <div className="divide-y rounded-xl border text-sm">
            {w.withdrawals.map((x) => (
              <div key={x.id} className="flex flex-wrap items-center justify-between gap-2 p-3">
                <span>{formatMoney(x.amount, x.currency)} · {tr(x.method === "bank" ? "Bank transfer" : "Mobile wallet")} ••••{x.account_last4}{x.reference ? ` · ${x.reference}` : ""}{x.note ? ` · ${x.note}` : ""}</span>
                <span className="flex items-center gap-2 text-muted-foreground">{formatDateTime(x.created_at)}<Badge variant={x.status === "paid" ? "default" : x.status === "rejected" ? "destructive" : "secondary"}>{tr(x.status === "paid" ? "Paid" : x.status === "rejected" ? "Not paid" : "Requested")}</Badge></span>
              </div>
            ))}
          </div>
        </>
      )}
    </div>
  );
}
