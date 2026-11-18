"use client";

import { useEffect, useState } from "react";

import { field, Note } from "@/components/installations/shared";
import { PageHeader } from "@/components/layout/page-header";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { errorMessage } from "@/lib/errors";
import { tr } from "@/lib/i18n";
import { giveGoodwill, sendRefundThrough,answerHelpRequest, approveRefundRequest, getHelpRequests, type HelpRequest, getOrderProblems, getRefundQueue, markProblemHandled, type OrderProblem, importProducts, markRefundSent, markPartialRefundSent, partialRefundOrder, sendPartialRefundThrough, refundOrder, rejectRefundRequest, collectReturn, inspectReturn, type RefundQueueItem } from "@/services/ops.service";
import { formatMoney } from "@/lib/i18n";
import { imageSrc } from "@/lib/surfaces";

type Result = { tone: "ok" | "warn"; text: string; rows?: { row: number; messages: string[] }[] };

/** A goodwill gesture in points when something went wrong but a refund is not the answer. */
function GoodwillForm({ orderId }: { orderId: number }) {
  const [open, setOpen] = useState(false);
  const [points, setPoints] = useState("100");
  const [why, setWhy] = useState("");
  const [msg, setMsg] = useState<{ ok: boolean; text: string } | null>(null);
  if (!open) return <button type="button" className="text-[0.8rem] underline" onClick={() => setOpen(true)}>{tr("Give goodwill points")}</button>;

  return (
    <form className="flex w-full flex-wrap items-center gap-2" data-testid="goodwill-form" onSubmit={async (e) => {
      e.preventDefault();
      setMsg(null);
      try { const r = await giveGoodwill(orderId, Number(points), why); setMsg({ ok: true, text: `${tr("Points given")}: ${r.points}` }); setWhy(""); } catch (x) { setMsg({ ok: false, text: errorMessage(x) }); }
    }}>
      <input type="number" min={1} className="h-8 w-24 rounded-lg border px-2" value={points} onChange={(e) => setPoints(e.target.value)} aria-label={tr("Points")} />
      <input className="h-8 min-w-[12rem] flex-1 rounded-lg border px-2" value={why} onChange={(e) => setWhy(e.target.value)} required minLength={3} maxLength={300} placeholder={tr("Why (the customer sees this)")} />
      <Button size="sm" type="submit">{tr("Give points")}</Button>
      {msg && <span role="status" className={msg.ok ? "text-green-700" : "text-red-600"}>{msg.text}</span>}
    </form>
  );
}

/** Two operations tools: giving a customer their money back, and adding many products from a spreadsheet. */
export default function AdminToolsPage() {
  const [refund, setRefund] = useState<Result | null>(null);
  const [partial, setPartial] = useState<Result | null>(null);
  const [imp, setImp] = useState<Result | null>(null);
  const [file, setFile] = useState<File | null>(null);
  const [queue, setQueue] = useState<RefundQueueItem[]>([]);
  const [queueErr, setQueueErr] = useState<string | null>(null);
  const [problems, setProblems] = useState<OrderProblem[]>([]);
  const [help, setHelp] = useState<HelpRequest[]>([]);
  const loadHelp = () => getHelpRequests().then(setHelp).catch(() => setHelp([]));
  useEffect(() => { loadHelp(); }, []);
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

      <section className="rounded-xl border p-4" data-testid="help-queue">
        <h2 className="mb-1 text-lg font-semibold">{tr("Help requests")}</h2>
        {help.length === 0 ? <p className="text-sm text-muted-foreground">{tr("No questions waiting.")}</p> : (
          <ul className="space-y-3">
            {help.map((h) => (
              <li key={h.id} className="rounded-lg border p-3 text-sm">
                <p><b>{h.subject}</b> · {h.customer}</p>
                {h.order_id ? <p className="text-xs text-muted-foreground">{tr("About order #{id}", { id: h.order_id })}</p> : null}
                <ul className="mb-2 space-y-1">{(h.messages ?? []).map((m) => <li key={m.id} className={`rounded-lg p-2 ${m.side === "staff" ? "bg-primary/10" : "bg-muted"}`}>{m.body}</li>)}</ul>
                <form className="flex gap-2" onSubmit={(e) => { e.preventDefault(); const f = new FormData(e.currentTarget); answerHelpRequest(h.id, String(f.get("reply"))).then(loadHelp).catch((x) => setQueueErr(errorMessage(x))); }}>
                  <input name="reply" className={field} placeholder={tr("Your answer")} required minLength={2} />
                  <Button type="submit" size="sm">{tr("Answer")}</Button>
                </form>
              </li>
            ))}
          </ul>
        )}
      </section>

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
                <GoodwillForm orderId={p.order_id} />
              </li>
            ))}
          </ul>
        )}
      </section>

      <section className="rounded-xl border p-4" data-testid="refund-queue">
        <h2 className="mb-1 text-lg font-semibold">{tr("Return requests")}</h2>
        <p className="mb-3 text-sm text-muted-foreground">{tr("Customers returning goods that already shipped: approve, collect the parcel with the customer's code, inspect it, and the refund follows a passed inspection.")}</p>
        {queueErr && <Note tone="warn">{queueErr}</Note>}
        {queue.length === 0 ? <p className="text-sm text-muted-foreground">{tr("No requests waiting.")}</p> : (
          <ul className="divide-y rounded-lg border">
            {queue.map((q) => (
              <li key={q.id} className="flex flex-wrap items-center justify-between gap-3 p-3 text-sm">
                <span><b>#{q.order_id}</b> · {q.product} · {formatMoney(q.amount, q.currency ?? "EGP")} · {q.customer}</span>
                <span className="text-muted-foreground">{q.reason}</span>
                {(q.photos ?? []).length > 0 && <span className="flex gap-1" data-testid="evidence">{q.photos!.map((p) => <a key={p} href={imageSrc(p) ?? "#"} target="_blank" rel="noreferrer">{/* eslint-disable-next-line @next/next/no-img-element -- evidence uploaded by the customer */}<img src={imageSrc(p) ?? ""} alt={tr("Evidence")} className="size-10 rounded border object-cover" /></a>)}</span>}
                <span className="flex flex-wrap items-center gap-2">
                  {q.status === "requested" && (<>
                    <Button size="sm" onClick={() => decide(() => approveRefundRequest(q.id))}>{tr("Approve return")}</Button>
                    <Button size="sm" variant="outline" onClick={() => { const note = window.prompt(tr("Why is it declined?")); if (note) decide(() => rejectRefundRequest(q.id, note)); }}>{tr("Decline")}</Button>
                  </>)}
                  {q.status === "awaiting_pickup" && (<>
                    <Badge variant="secondary">{tr("Waiting for collection")}{q.pickup_day ? ` · ${q.pickup_day}` : ""}</Badge>
                    <Button size="sm" onClick={() => { const code = window.prompt(tr("Enter the return code the customer gave")); if (code) decide(() => collectReturn(q.id, code)); }}>{tr("Collect with code")}</Button>
                  </>)}
                  {q.status === "collected" && (<>
                    <Badge variant="secondary">{tr("Collected")}</Badge>
                    <Button size="sm" onClick={() => { const note = window.prompt(tr("Inspection note (optional)")) ?? undefined; decide(() => inspectReturn(q.id, true, note)); }}>{tr("Passed: refund")}</Button>
                    <Button size="sm" variant="outline" onClick={() => { const note = window.prompt(tr("What is wrong with the parcel?")); if (note) decide(() => inspectReturn(q.id, false, note)); }}>{tr("Failed")}</Button>
                  </>)}
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
          <Button type="button" variant="outline" onClick={async (e) => {
            const form = (e.currentTarget as HTMLButtonElement).form;
            const id = Number(new FormData(form as HTMLFormElement).get("order"));
            if (!id) return;
            try { const r = await sendRefundThrough(id); setRefund({ tone: "ok", text: `${tr("Refund sent through the provider")}: ${r.reference}` }); } catch (x) { setRefund({ tone: "warn", text: errorMessage(x) }); }
          }}>{tr("Send through the provider")}</Button>
        </form>
      </section>

      <section className="rounded-xl border p-4" data-testid="partial-refund">
        <h2 className="mb-1 text-lg font-semibold">{tr("Refund part of an order")}</h2>
        <p className="mb-3 text-sm text-muted-foreground">{tr("Gives back part of the payment while the order stands (damage, a missing item, lateness). The seller and ArmorHub give up the same share of their cut. Once per order; it cannot be combined with a full refund.")}</p>
        {partial && <Note tone={partial.tone}>{partial.text}</Note>}
        <form
          className="flex flex-wrap gap-2"
          onSubmit={async (e) => {
            e.preventDefault();
            const f = new FormData(e.currentTarget);
            const id = Number(f.get("order"));
            try {
              if (f.get("reference")) { await markPartialRefundSent(id, String(f.get("reference"))); setPartial({ tone: "ok", text: tr("Marked as sent") }); }
              else { const r = await partialRefundOrder(id, Number(f.get("amount")), String(f.get("reason"))); setPartial({ tone: "ok", text: `${tr("Refund recorded")}: ${r.partial_refund_amount} ${r.currency}` }); }
            } catch (x) { setPartial({ tone: "warn", text: errorMessage(x) }); }
          }}
        >
          <input name="order" type="number" min={1} className={`${field} w-32`} placeholder={tr("Order number")} required />
          <input name="amount" type="number" min={0.01} step="0.01" className={`${field} w-32`} placeholder={tr("Amount")} />
          <input name="reason" className={`${field} max-w-xs`} placeholder={tr("Reason (the customer sees this)")} minLength={3} maxLength={300} />
          <input name="reference" className={`${field} max-w-xs`} placeholder={tr("Transfer reference (after sending the money)")} maxLength={120} />
          <Button type="submit">{tr("Save")}</Button>
          <Button type="button" variant="outline" onClick={async (e) => {
            const form = (e.currentTarget as HTMLButtonElement).form;
            const id = Number(new FormData(form as HTMLFormElement).get("order"));
            if (!id) return;
            try { const r = await sendPartialRefundThrough(id); setPartial({ tone: "ok", text: `${tr("Refund sent through the provider")}: ${r.reference}` }); } catch (x) { setPartial({ tone: "warn", text: errorMessage(x) }); }
          }}>{tr("Send through the provider")}</Button>
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
