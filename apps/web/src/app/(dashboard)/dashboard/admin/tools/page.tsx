"use client";

import { useEffect, useState } from "react";

import { field, Note } from "@/components/installations/shared";
import { PageHeader } from "@/components/layout/page-header";
import { Button } from "@/components/ui/button";
import { errorMessage } from "@/lib/errors";
import { tr } from "@/lib/i18n";
import { approveRefundRequest, getOrderProblems, getRefundQueue, markProblemHandled, type OrderProblem, importProducts, markRefundSent, refundOrder, rejectRefundRequest, type RefundQueueItem } from "@/services/ops.service";
import { formatMoney } from "@/lib/i18n";

type Result = { tone: "ok" | "warn"; text: string; rows?: { row: number; messages: string[] }[] };

/** Two operations tools: giving a customer their money back, and adding many products from a spreadsheet. */
export default function AdminToolsPage() {
  const [refund, setRefund] = useState<Result | null>(null);
  const [imp, setImp] = useState<Result | null>(null);
  const [file, setFile] = useState<File | null>(null);
  const [queue, setQueue] = useState<RefundQueueItem[]>([]);
  const [queueErr, setQueueErr] = useState<string | null>(null);
  const [problems, setProblems] = useState<OrderProblem[]>([]);
  const loadProblems = () => getOrderProblems().then(setProblems).catch(() => setProblems([]));
  useEffect(() => { loadProblems(); }, []);
  const loadQueue = () => getRefundQueue().then(setQueue).catch(() => setQueue([]));
  useEffect(() => { loadQueue(); }, []);
  async function decide(fn: () => Promise<unknown>) {
    setQueueErr(null);
    try { await fn(); await loadQueue(); } catch (e) { setQueueErr(errorMessage(e)); }
  }

  async function doImport(dry: boolean) {
    if (!file) { setImp({ tone: "warn", text: tr("Choose a file first.") }); return; }
    try {
      const r = await importProducts(file, dry);
      setImp({ tone: "ok", text: dry ? `${tr("The file is fine")}: ${r.rows} ${tr("products")}` : `${tr("Imported")}: ${r.imported}` });
    } catch (e: unknown) {
      const data = (e as { response?: { data?: { errors?: { row: number; messages: string[] }[] } } }).response?.data;
      setImp({ tone: "warn", text: data?.errors ? tr("Some rows have problems. Nothing was imported.") : errorMessage(e), rows: data?.errors });
    }
  }

  return (
    <div className="space-y-8">
      <PageHeader title={tr("Operations tools")} />

      <section className="rounded-xl border p-4" data-testid="problem-queue">
        <h2 className="mb-1 text-lg font-semibold">{tr("Problems reported by customers")}</h2>
        {problems.length === 0 ? <p className="text-sm text-muted-foreground">{tr("No problems waiting.")}</p> : (
          <ul className="divide-y rounded-lg border">
            {problems.map((p) => (
              <li key={p.id} className="flex flex-wrap items-center justify-between gap-3 p-3 text-sm">
                <span><b>#{p.order_id}</b> · {p.product} · {p.customer}</span>
                <span className="text-muted-foreground">{p.body}</span>
                <span className="flex gap-2">
                  <a className="inline-flex h-8 items-center rounded-full border px-4 text-[0.8rem] font-semibold" href={`/dashboard/orders/${p.order_id}`}>{tr("Open the order")}</a>
                  <Button size="sm" onClick={() => markProblemHandled(p.id).then(loadProblems)}>{tr("Mark as handled")}</Button>
                </span>
              </li>
            ))}
          </ul>
        )}
      </section>

      <section className="rounded-xl border p-4" data-testid="refund-queue">
        <h2 className="mb-1 text-lg font-semibold">{tr("Return requests")}</h2>
        <p className="mb-3 text-sm text-muted-foreground">{tr("Customers asking to return goods that already shipped. Approving refunds them in full.")}</p>
        {queueErr && <Note tone="warn">{queueErr}</Note>}
        {queue.length === 0 ? <p className="text-sm text-muted-foreground">{tr("No requests waiting.")}</p> : (
          <ul className="divide-y rounded-lg border">
            {queue.map((q) => (
              <li key={q.id} className="flex flex-wrap items-center justify-between gap-3 p-3 text-sm">
                <span><b>#{q.order_id}</b> · {q.product} · {formatMoney(q.amount, q.currency ?? "EGP")} · {q.customer}</span>
                <span className="text-muted-foreground">{q.reason}</span>
                <span className="flex gap-2">
                  <Button size="sm" onClick={() => decide(() => approveRefundRequest(q.id))}>{tr("Approve")}</Button>
                  <Button size="sm" variant="outline" onClick={() => { const note = window.prompt(tr("Why is it declined?")); if (note) decide(() => rejectRefundRequest(q.id, note)); }}>{tr("Decline")}</Button>
                </span>
              </li>
            ))}
          </ul>
        )}
      </section>

      <section className="rounded-xl border p-4">
        <h2 className="mb-1 text-lg font-semibold">{tr("Refund an order")}</h2>
        <p className="mb-3 text-sm text-muted-foreground">{tr("Gives the customer the full amount back. Not possible once the seller has been paid.")}</p>
        {refund && <Note tone={refund.tone}>{refund.text}</Note>}
        <form
          className="flex flex-wrap gap-2"
          onSubmit={async (e) => {
            e.preventDefault();
            const f = new FormData(e.currentTarget);
            const id = Number(f.get("order"));
            try {
              if (f.get("reference")) { await markRefundSent(id, String(f.get("reference"))); setRefund({ tone: "ok", text: tr("Marked as sent") }); }
              else { const r = await refundOrder(id, String(f.get("reason"))); setRefund({ tone: "ok", text: `${tr("Refund recorded")}: ${r.refund_amount} ${r.currency}` }); }
            } catch (x) { setRefund({ tone: "warn", text: errorMessage(x) }); }
          }}
        >
          <input name="order" type="number" min={1} className={`${field} w-32`} placeholder={tr("Order number")} required />
          <input name="reason" className={`${field} max-w-xs`} placeholder={tr("Reason")} minLength={3} maxLength={300} />
          <input name="reference" className={`${field} max-w-xs`} placeholder={tr("Transfer reference (after sending the money)")} maxLength={120} />
          <Button type="submit">{tr("Save")}</Button>
        </form>
      </section>

      <section className="rounded-xl border p-4">
        <h2 className="mb-1 text-lg font-semibold">{tr("Import products")}</h2>
        <p className="mb-3 text-sm text-muted-foreground">{tr("A CSV or Excel file, first row = column names. Needs name, category and base_price; optional sku, brand, description. Up to 500 rows, all or nothing.")}</p>
        {imp && <Note tone={imp.tone}>{imp.text}</Note>}
        {imp?.rows && <ul className="mb-3 list-disc ps-5 text-sm" data-testid="import-errors">{imp.rows.map((r) => <li key={r.row}>{tr("Row")} {r.row}: {r.messages.join(" ")}</li>)}</ul>}
        <div className="flex flex-wrap items-center gap-2">
          <input type="file" accept=".csv,.xlsx" onChange={(e) => setFile(e.target.files?.[0] ?? null)} aria-label={tr("File")} />
          <Button variant="outline" onClick={() => doImport(true)}>{tr("Check file")}</Button>
          <Button onClick={() => doImport(false)}>{tr("Import")}</Button>
        </div>
      </section>
    </div>
  );
}
