"use client";

import { useEffect, useState } from "react";

import { Note } from "@/components/installations/shared";
import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { LoadingBlock } from "@/components/ui/loading-block";
import { errorMessage } from "@/lib/errors";
import { tr } from "@/lib/i18n";
import { decideWarrantyClaim, getWarrantyClaimInbox } from "@/services/platform.service";
import type { WarrantyClaim } from "@/types/platform";

const RESOLUTIONS = [["repair", "Repair"], ["replace", "Replacement"], ["refund", "Refund"]] as const;

/** Warranty claims on this seller's goods: approve with a repair, a replacement or a refund, or decline with a reason; then mark it resolved. */
export default function WarrantyClaimsPage() {
  const [rows, setRows] = useState<WarrantyClaim[] | null>(null);
  const [notes, setNotes] = useState<Record<number, string>>({});
  const [how, setHow] = useState<Record<number, string>>({});
  const [err, setErr] = useState<string | null>(null);
  const load = () => getWarrantyClaimInbox().then(setRows).catch(() => setRows([]));
  useEffect(() => { load(); }, []);

  async function act(id: number, status: "approved" | "rejected" | "resolved") {
    setErr(null);
    try { await decideWarrantyClaim(id, { status, resolution: how[id] ?? "repair", note: notes[id] }); await load(); } catch (e) { setErr(errorMessage(e)); }
  }

  return (
    <div className="space-y-4">
      <PageHeader title={tr("Warranty claims")} />
      {err && <Note tone="warn">{err}</Note>}
      {rows === null ? <LoadingBlock /> : rows.length === 0 ? <EmptyState>{tr("No warranty claims.")}</EmptyState> : rows.map((c) => (
        <section key={c.id} className="rounded-xl border p-4" data-testid="warranty-claim">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <p className="font-semibold">{c.product?.name} <span className="font-normal text-muted-foreground">· {c.customer}</span></p>
            <Badge variant={c.status === "open" ? "default" : "secondary"}>{tr(({ open: "Needs a decision", approved: "Approved", rejected: "Declined", resolved: "Resolved" } as const)[c.status])}</Badge>
          </div>
          <p className="mt-2 text-sm">{c.description}</p>
          {c.status === "open" && (
            <div className="mt-3 grid gap-2 sm:grid-cols-[auto_1fr_auto_auto]">
              <select className="h-10 rounded-[10px] border bg-background px-2 text-sm" aria-label={tr("How will it be put right?")} value={how[c.id] ?? "repair"} onChange={(e) => setHow((h) => ({ ...h, [c.id]: e.target.value }))}>
                {RESOLUTIONS.map(([k, l]) => <option key={k} value={k}>{tr(l)}</option>)}
              </select>
              <input className="h-10 rounded-[10px] border bg-background px-3 text-sm" maxLength={300} placeholder={tr("A note for the customer (required to decline)")} aria-label={tr("A note for the customer (required to decline)")} value={notes[c.id] ?? ""} onChange={(e) => setNotes((n) => ({ ...n, [c.id]: e.target.value }))} />
              <Button size="sm" onClick={() => act(c.id, "approved")}>{tr("Approve")}</Button>
              <Button size="sm" variant="outline" onClick={() => act(c.id, "rejected")}>{tr("Decline")}</Button>
            </div>
          )}
          {c.status === "approved" && (
            <div className="mt-3 flex flex-wrap items-center gap-2 text-sm">
              <span>{c.resolution && tr(({ repair: "Repair", replace: "Replacement", refund: "Refund" } as const)[c.resolution])}{c.note ? ` · ${c.note}` : ""}</span>
              <Button size="sm" onClick={() => act(c.id, "resolved")}>{tr("Mark as resolved")}</Button>
            </div>
          )}
        </section>
      ))}
    </div>
  );
}
