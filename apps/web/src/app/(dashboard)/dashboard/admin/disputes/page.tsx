"use client";

import { useEffect, useState } from "react";

import { field, Note, ProofGallery } from "@/components/installations/shared";
import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { errorMessage } from "@/lib/errors";
import { DISPUTE_TYPES } from "@/lib/installation";
import { formatDateTime, formatMoney, tr } from "@/lib/i18n";
import { decideDispute, decideViolation, getAdminDisputes, getViolations, markRefundPaid, type AdminDispute } from "@/services/installation.service";

function Decide({ d, onDone }: { d: AdminDispute; onDone: () => void }) {
  const [decision, setDecision] = useState("release");
  const [amount, setAmount] = useState("");
  const [note, setNote] = useState("");
  const [err, setErr] = useState<string | null>(null);

  return (
    <form className="mt-3 space-y-2 rounded-lg bg-muted p-3" onSubmit={async (e) => {
      e.preventDefault(); setErr(null);
      try { await decideDispute(d.id, { decision, note, ...(decision === "refund_partial" ? { refund_amount: Number(amount) } : {}) }); onDone(); } catch (x) { setErr(errorMessage(x)); }
    }}>
      <select aria-label={tr("Decision")} className={field} value={decision} onChange={(e) => setDecision(e.target.value)}>
        <option value="release">{tr("Release the payment to the centre")}</option>
        <option value="refund_partial">{tr("Partial refund to the customer")}</option>
        <option value="refund_full">{tr("Full refund to the customer")}</option>
      </select>
      {decision === "refund_partial" && <Input aria-label={tr("Refund amount")} type="number" min={1} step="0.01" required placeholder={`${tr("Refund amount")} (< ${d.job.pay_amount})`} value={amount} onChange={(e) => setAmount(e.target.value)} />}
      <Input aria-label={tr("Reason for the decision")} required minLength={5} placeholder={tr("Reason for the decision (both sides will see it)")} value={note} onChange={(e) => setNote(e.target.value)} />
      {err && <p role="alert" className="text-sm text-red-600">{err}</p>}
      <Button type="submit">{tr("Decide")}</Button>
    </form>
  );
}

function RefundBox({ d, onDone }: { d: AdminDispute; onDone: () => void }) {
  const [ref, setRef] = useState("");
  if (d.job.refund_status !== "pending") return d.job.refund_status === "refunded" ? <Note tone="ok">{tr("Refund sent")}: {d.job.refund_reference}</Note> : null;

  return (
    <form className="mt-3 flex gap-2" onSubmit={async (e) => { e.preventDefault(); await markRefundPaid(d.job.id, ref); onDone(); }}>
      <Input aria-label={tr("Bank or provider reference")} required placeholder={tr("Reference of the refund you sent")} value={ref} onChange={(e) => setRef(e.target.value)} />
      <Button type="submit" variant="outline">{tr("Mark refund sent")}</Button>
    </form>
  );
}

export default function AdminDisputesPage() {
  const [rows, setRows] = useState<AdminDispute[] | null>(null);
  const [viol, setViol] = useState<Awaited<ReturnType<typeof getViolations>>>([]);
  const [forbidden, setForbidden] = useState(false);
  const load = () => { getAdminDisputes().then(setRows).catch(() => setForbidden(true)); getViolations().then(setViol).catch(() => undefined); };
  useEffect(() => { load(); }, []);

  if (forbidden) return <EmptyState>{tr("Only platform admins can open this page.")}</EmptyState>;
  if (!rows) return <p className="text-muted-foreground">{tr("Loading...")}</p>;

  return (
    <div className="max-w-4xl">
      <PageHeader title="Disputes" />
      <p className="mb-6 text-sm text-muted-foreground">{tr("While a dispute is open the payment stays on hold. Look at the evidence from both sides, then release the payment or refund the customer. Money is never moved by this page: record the reference of the refund you send.")}</p>

      {rows.length === 0 ? <EmptyState>{tr("No disputes.")}</EmptyState> : rows.map((d) => (
        <section key={d.id} className="mb-6 rounded-xl border p-4" data-testid="dispute">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <p className="font-medium">#{d.job.id} · {d.job.subject} · {d.job.parts.join(", ")}</p>
            <Badge variant={d.status === "open" ? "destructive" : "default"}>{tr(d.status === "open" ? "Open" : "Decided")}</Badge>
          </div>
          <p className="text-sm text-muted-foreground">{d.job.customer} → {d.job.centre} · {formatMoney(d.job.pay_amount, d.job.currency)} · {formatDateTime(d.created_at)}</p>
          <p className="mt-2 text-sm"><strong>{tr(d.side === "customer" ? "Customer" : "Centre")}</strong>: {tr(DISPUTE_TYPES.find(([v]) => v === d.type)?.[1] ?? "Something else")}. {d.description}</p>
          <div className="mt-3"><ProofGallery proofs={d.evidence.map((e) => ({ ...e, side: e.side as "centre" | "customer" }))} /></div>
          {d.status === "open" ? <Decide d={d} onDone={load} /> : <>
            <Note>{tr("Decision")}: {d.decision} {d.refund_amount ? formatMoney(d.refund_amount, d.job.currency) : ""} · {d.decision_note}</Note>
            <RefundBox d={d} onDone={load} />
          </>}
        </section>
      ))}

      <h2 className="mb-2 mt-8 text-xl font-semibold">{tr("Reports of dealing outside the platform")}</h2>
      {viol.length === 0 ? <EmptyState>{tr("No reports.")}</EmptyState> : (
        <div className="divide-y rounded-xl border text-sm">
          {viol.map((v) => (
            <div key={v.id} className="flex flex-wrap items-center justify-between gap-2 p-3">
              <span>#{v.request_id} · {v.centre} ({v.strikes}/3) {v.note ? `· ${v.note}` : ""}</span>
              {v.status === "open" ? (
                <span className="flex gap-2">
                  <Button size="sm" onClick={async () => { await decideViolation(v.id, "confirmed"); load(); }}>{tr("Confirm and give a strike")}</Button>
                  <Button size="sm" variant="outline" onClick={async () => { await decideViolation(v.id, "dismissed"); load(); }}>{tr("Dismiss")}</Button>
                </span>
              ) : <Badge variant="secondary">{tr(v.status === "confirmed" ? "Confirmed" : "Dismissed")}</Badge>}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
