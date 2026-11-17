"use client";

import { useEffect, useState } from "react";

import { field, Note } from "@/components/installations/shared";
import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { errorMessage } from "@/lib/errors";
import { formatDateTime, tr } from "@/lib/i18n";
import { acceptQuote, closeQuoteRequest, createQuoteRequest, getQuoteRequests, type QuoteRequest } from "@/services/ops.service";

/** Asking sellers for a price on something not sold from a shelf, then choosing between their numbered quotes. */
export default function QuotesPage() {
  const [rows, setRows] = useState<QuoteRequest[] | null>(null);
  const [err, setErr] = useState<string | null>(null);
  const [adding, setAdding] = useState(false);
  const [accepting, setAccepting] = useState<{ req: number; quote: number } | null>(null);
  const load = () => getQuoteRequests().then(setRows).catch(() => setRows([]));
  useEffect(() => { load(); }, []);

  async function run(fn: () => Promise<unknown>) {
    setErr(null);
    try { await fn(); setAdding(false); setAccepting(null); await load(); } catch (e) { setErr(errorMessage(e)); }
  }

  return (
    <div className="space-y-6">
      <PageHeader title={tr("Price requests")} action={<Button onClick={() => setAdding(true)}>{tr("New request")}</Button>} />
      {err && <Note tone="warn">{err}</Note>}

      {adding && (
        <form
          className="grid gap-3 rounded-xl border p-4 md:grid-cols-2"
          onSubmit={(e) => {
            e.preventDefault();
            const f = new FormData(e.currentTarget);
            run(() => createQuoteRequest({ title: String(f.get("title")), quantity: Number(f.get("quantity")), notes: String(f.get("notes") || "") || undefined }));
          }}
        >
          <input name="title" className={field} placeholder={tr("What do you need?")} required maxLength={160} />
          <input name="quantity" type="number" min={1} defaultValue={1} className={field} required />
          <textarea name="notes" className="rounded-[10px] border p-2 text-sm md:col-span-2" placeholder={tr("Details for the sellers (no contact details)")} maxLength={600} />
          <div className="flex gap-2"><Button type="submit">{tr("Send request")}</Button><Button type="button" variant="outline" onClick={() => setAdding(false)}>{tr("Cancel")}</Button></div>
        </form>
      )}

      {rows === null ? <p className="text-muted-foreground">{tr("Loading...")}</p> : rows.length === 0 ? <EmptyState>{tr("No price requests yet.")}</EmptyState> : rows.map((r) => (
        <section key={r.id} className="rounded-xl border p-4" data-testid="quote-request">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <p className="font-semibold">{r.number} · {r.title ?? r.category} × {r.quantity}</p>
            <div className="flex items-center gap-2"><Badge variant="secondary">{r.status}</Badge>{r.status === "open" && <Button size="sm" variant="outline" onClick={() => run(() => closeQuoteRequest(r.id))}>{tr("Close")}</Button>}</div>
          </div>
          <p className="mb-3 text-sm text-muted-foreground">{tr("Open until")} {formatDateTime(r.expires_at)}</p>
          {r.quotes.length === 0 ? <p className="text-sm text-muted-foreground">{tr("No quotes yet. Sellers have been asked.")}</p> : (
            <ul className="divide-y rounded-lg border">
              {r.quotes.map((q) => (
                <li key={q.id} className="flex flex-wrap items-center justify-between gap-3 p-3 text-sm">
                  <span><b>{q.number}</b> · {q.seller}</span>
                  <span>{q.total} ({q.unit_price} × {r.quantity})</span>
                  <span className="text-muted-foreground">{q.lead_days ?? 0} {tr("days")}{q.condition ? ` · ${q.condition}` : ""}{q.warranty_days ? ` · ${q.warranty_days} ${tr("days warranty")}` : ""}</span>
                  <span className="text-muted-foreground">{tr("Valid until")} {formatDateTime(q.valid_until)}</span>
                  {r.status === "open" && !q.expired && q.status === "submitted" ? <Button size="sm" onClick={() => setAccepting({ req: r.id, quote: q.id })}>{tr("Accept")}</Button> : <Badge variant="outline">{q.expired ? tr("Expired") : q.status}</Badge>}
                </li>
              ))}
            </ul>
          )}
          {accepting?.req === r.id && (
            <form
              className="mt-3 grid gap-2 rounded-lg bg-muted/40 p-3 md:grid-cols-2"
              onSubmit={(e) => {
                e.preventDefault();
                const f = new FormData(e.currentTarget);
                run(() => acceptQuote(r.id, accepting.quote, { name: String(f.get("name")), phone: String(f.get("phone")), line1: String(f.get("line1")), city: String(f.get("city")) }));
              }}
            >
              <p className="text-sm font-medium md:col-span-2">{tr("Where should it be delivered?")}</p>
              {(["name", "phone", "line1", "city"] as const).map((n) => <input key={n} name={n} className={field} placeholder={tr({ name: "Full name", phone: "Phone", line1: "Address", city: "City" }[n])} required />)}
              <div className="flex gap-2 md:col-span-2"><Button type="submit">{tr("Accept and continue")}</Button><Button type="button" variant="outline" onClick={() => setAccepting(null)}>{tr("Cancel")}</Button></div>
            </form>
          )}
        </section>
      ))}
    </div>
  );
}
