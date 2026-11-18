"use client";

import { useEffect, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { Badge } from "@/components/ui/badge";
import { formatDateTime, formatMoney, tr } from "@/lib/i18n";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { errorMessage } from "@/lib/errors";
import { getPayoutAccount, getSellerEarnings, requestPayout, savePayoutAccount } from "@/services/platform.service";
import { LoadingBlock } from "@/components/ui/loading-block";

type Earnings = Awaited<ReturnType<typeof getSellerEarnings>>;

/** What the seller is owed: held until the buyer has the goods, available to be paid, and already paid. */
const TYPES: Record<string, string> = { sale: "Sale", commission: "Platform commission", shipping: "Shipping", refund_debt: "Refund taken back", partial_refund: "Part refunded" };

export default function EarningsPage() {
  const [data, setData] = useState<Earnings | null>(null);
  const [forbidden, setForbidden] = useState(false);
  const [acct, setAcct] = useState<Awaited<ReturnType<typeof getPayoutAccount>> | null>(null);
  const [note, setNote] = useState<{ ok: boolean; text: string } | null>(null);
  const [tick, setTick] = useState(0);

  useEffect(() => {
    getSellerEarnings().then(setData).catch(() => setForbidden(true));
    getPayoutAccount().then(setAcct).catch(() => setAcct(null));
  }, [tick]);

  if (forbidden) return <EmptyState>{tr("You do not have access to earnings.")}</EmptyState>;
  if (!data) return <LoadingBlock />;

  const currencies = Object.keys(data.balances);
  const cards: [keyof Earnings["balances"][string], string][] = [["held", "Held until delivery"], ["available", "Available to pay out"], ["in_payout", "In a payout"], ["paid", "Paid out"]];

  return (
    <div className="max-w-3xl">
      <PageHeader title={tr("Earnings")} />
      <p className="mb-6 text-sm text-muted-foreground">{tr("A sale's money is held until the customer receives the goods, then becomes available. The platform pays it out and records the bank reference here. Amounts are after the platform's commission.")}</p>

      <section className="mb-8 rounded-xl border p-4" data-testid="payout-account">
        <h2 className="mb-1 text-lg font-semibold">{tr("Where to pay you")}</h2>
        {acct?.account && (
          <p className="mb-3 text-sm">
            <b>{acct.account.method === "bank" ? acct.account.bank_name : tr("Mobile wallet")}</b> · <span dir="ltr">{acct.account.number}</span> · {acct.account.holder}{" "}
            <Badge variant={acct.account.status === "verified" ? "default" : "secondary"}>{tr(acct.account.status === "verified" ? "Verified" : acct.account.status === "rejected" ? "Not accepted" : "Waiting to be checked")}</Badge>
            {acct.account.status === "rejected" && acct.account.note ? <span className="block text-red-600">{acct.account.note}</span> : null}
          </p>
        )}
        {note && <p className={`mb-2 text-sm ${note.ok ? "text-green-700" : "text-red-600"}`} role="status">{note.text}</p>}
        <form className="grid gap-2 sm:grid-cols-2" onSubmit={async (e) => {
          e.preventDefault();
          const f = new FormData(e.currentTarget);
          const method = String(f.get("method")) as "bank" | "wallet";
          try {
            await savePayoutAccount({ method, holder: String(f.get("holder")), ...(method === "bank" ? { bank_name: String(f.get("bank_name")), account_number: String(f.get("number")) } : { wallet_phone: String(f.get("number")) }) });
            setNote({ ok: true, text: tr("Saved. We will check it before paying into it.") });
            setTick((t) => t + 1);
          } catch (x) { setNote({ ok: false, text: errorMessage(x) }); }
        }}>
          <select name="method" className="h-10 rounded-[10px] border bg-background px-2 text-sm" aria-label={tr("Type")}><option value="bank">{tr("Bank account")}</option><option value="wallet">{tr("Mobile wallet")}</option></select>
          <Input name="holder" required minLength={3} placeholder={tr("Account holder name")} aria-label={tr("Account holder name")} />
          <Input name="bank_name" placeholder={tr("Bank name (for a bank)")} aria-label={tr("Bank name (for a bank)")} />
          <Input name="number" required dir="ltr" placeholder={tr("IBAN / account number, or wallet phone")} aria-label={tr("IBAN / account number, or wallet phone")} />
          <Button type="submit" className="w-fit">{tr("Save account")}</Button>
        </form>
      </section>

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
            {data.balances[c].available < 0 && <p className="col-span-2 text-sm text-red-600 md:col-span-4" data-testid="debt">{tr("You owe {amount} for refunded orders. It is taken from your next earnings.", { amount: formatMoney(-data.balances[c].available, c) })}</p>}
            <div className="col-span-2 md:col-span-4">
              <Button disabled={data.balances[c].available <= 0 || acct?.account?.status !== "verified"} onClick={async () => { try { await requestPayout(c); setNote({ ok: true, text: tr("Payout requested. We will pay it and record the reference here.") }); setTick((t) => t + 1); } catch (x) { setNote({ ok: false, text: errorMessage(x) }); } }}>{tr("Request payout")}</Button>
              {acct && <span className="ms-3 text-xs text-muted-foreground">{tr("Smallest payout: {amount}", { amount: formatMoney(acct.min_amount, c) })}</span>}
            </div>
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
                <span>{tr(TYPES[e.type] ?? "Platform commission")} · {tr("Order #{id}", { id: e.order_id })}{e.status === "held" && e.release_at ? <span className="block text-xs text-muted-foreground">{tr("Unlocks on {date}", { date: formatDateTime(e.release_at) })}</span> : null}</span>
                <span className="flex items-center gap-2"><strong>{formatMoney(e.amount, e.currency)}</strong><Badge variant="secondary">{tr(e.status === "held" ? "Held" : e.status === "available" ? "Available" : "Paid")}</Badge></span>
              </div>
            ))}
          </div>
        </>
      )}
    </div>
  );
}
