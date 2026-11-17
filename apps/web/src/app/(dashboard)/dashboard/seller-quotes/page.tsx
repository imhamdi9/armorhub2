"use client";

import { useEffect, useState } from "react";

import { field, Note } from "@/components/installations/shared";
import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { errorMessage } from "@/lib/errors";
import { formatDateTime, tr } from "@/lib/i18n";
import { getSellerRequests, sendSellerQuote, withdrawSellerQuote, type SellerRequest } from "@/services/ops.service";

/** Price requests sent to this seller. The buyer's identity is never shown. */
export default function SellerQuotesPage() {
  const [rows, setRows] = useState<SellerRequest[] | null>(null);
  const [err, setErr] = useState<string | null>(null);
  const load = () => getSellerRequests().then(setRows).catch(() => setRows([]));
  useEffect(() => { load(); }, []);

  async function run(fn: () => Promise<unknown>) {
    setErr(null);
    try { await fn(); await load(); } catch (e) { setErr(errorMessage(e)); }
  }

  return (
    <div className="space-y-4">
      <PageHeader title={tr("Price requests for you")} />
      {err && <Note tone="warn">{err}</Note>}
      {rows === null ? <p className="text-muted-foreground">{tr("Loading...")}</p> : rows.length === 0 ? <EmptyState>{tr("No open price requests.")}</EmptyState> : rows.map((r) => (
        <section key={r.id} className="rounded-xl border p-4" data-testid="seller-request">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <p className="font-semibold">{r.number} · {r.title ?? r.category} × {r.quantity}</p>
            <span className="text-sm text-muted-foreground">{tr("Open until")} {formatDateTime(r.expires_at)}</span>
          </div>
          {r.notes && <p className="mt-1 text-sm text-muted-foreground">{r.notes}</p>}
          {r.my_quote ? (
            <div className="mt-3 flex flex-wrap items-center justify-between gap-2 text-sm">
              <span>{tr("Your quote")} {r.my_quote.number}: {r.my_quote.unit_price} <Badge variant="secondary">{r.my_quote.status}</Badge></span>
              {r.my_quote.status === "submitted" && <Button size="sm" variant="outline" onClick={() => run(() => withdrawSellerQuote(r.id))}>{tr("Withdraw")}</Button>}
            </div>
          ) : (
            <form
              className="mt-3 flex flex-wrap gap-2"
              onSubmit={(e) => {
                e.preventDefault();
                const f = new FormData(e.currentTarget);
                run(() => sendSellerQuote(r.id, { unit_price: Number(f.get("price")), lead_days: Number(f.get("lead")) || undefined, condition: String(f.get("condition")) || undefined, warranty_days: Number(f.get("warranty")) || undefined }));
              }}
            >
              <input name="price" type="number" step="0.01" min="0.01" className={`${field} w-32`} placeholder={tr("Unit price")} required />
              <input name="lead" type="number" min={0} className={`${field} w-28`} placeholder={tr("Days to deliver")} />
              <select name="condition" className={`${field} w-36`} defaultValue="new"><option value="new">{tr("New")}</option><option value="oem">OEM</option><option value="aftermarket">{tr("Aftermarket")}</option><option value="used">{tr("Used")}</option></select>
              <input name="warranty" type="number" min={0} className={`${field} w-32`} placeholder={tr("Warranty (days)")} />
              <Button type="submit">{tr("Send quote")}</Button>
            </form>
          )}
        </section>
      ))}
    </div>
  );
}
