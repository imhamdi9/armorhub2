"use client";

import { useEffect, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { errorMessage } from "@/lib/errors";
import { fileWarrantyClaim, getWarranties } from "@/services/platform.service";
import type { Warranty } from "@/types/platform";
import { formatDate, tr } from "@/lib/i18n";
import { LoadingBlock } from "@/components/ui/loading-block";

const CLAIM_LABEL = { open: "Claim sent", approved: "Claim approved", rejected: "Claim declined", resolved: "Claim resolved" } as const;
const RESOLUTION_LABEL = { repair: "Repair", replace: "Replacement", refund: "Refund" } as const;

export default function WarrantiesPage() {
  const [items, setItems] = useState<Warranty[] | null>(null);
  const [text, setText] = useState<Record<number, string>>({});
  const [err, setErr] = useState<string | null>(null);
  const load = () => getWarranties().then(setItems).catch(() => setItems([]));

  useEffect(() => {
    load();
  }, []);

  return (
    <div>
      <PageHeader title={tr("Warranties")} />

      {err && <p className="mb-3 text-sm text-red-600" role="alert">{err}</p>}
      {items === null ? (
        <LoadingBlock />
      ) : items.length === 0 ? (
        <EmptyState>{tr("No warranties yet. One is issued when a warranted service is completed.")}</EmptyState>
      ) : (
        <div className="space-y-3">
          {items.map((w) => (
            <div key={w.id} className="rounded-lg border p-4">
              <div className="flex items-center justify-between">
              <div>
                <p className="font-semibold">{w.product?.name}</p>
                <p className="text-sm text-muted-foreground">
                  {tr("Order #{id}", { id: w.order_id })} · {formatDate(w.starts_at)} –{" "}
                  {formatDate(w.ends_at)}
                </p>
              </div>
              <Badge variant={w.active ? "default" : "outline"}>{w.active ? tr("Active") : tr("Expired")}</Badge>
              </div>
              {w.claims && w.claims.length > 0 && (
                <ul className="mt-3 space-y-1 text-sm" data-testid="claims">
                  {w.claims.map((c) => (
                    <li key={c.id} className="rounded-lg bg-muted p-2">
                      <Badge variant="secondary">{tr(CLAIM_LABEL[c.status])}</Badge>{" "}
                      {c.resolution && <b>{tr(RESOLUTION_LABEL[c.resolution])} </b>}
                      <span className="text-muted-foreground">{c.description}</span>
                      {c.note && <span className="block">“{c.note}”</span>}
                    </li>
                  ))}
                </ul>
              )}
              {w.can_claim && (
                <form className="mt-3 grid gap-2" data-testid="claim-form" onSubmit={async (e) => {
                  e.preventDefault();
                  setErr(null);
                  try { await fileWarrantyClaim(w.id, text[w.id] ?? ""); setText((t) => ({ ...t, [w.id]: "" })); await load(); } catch (x) { setErr(errorMessage(x)); }
                }}>
                  <textarea className="rounded-[10px] border p-2 text-sm" rows={2} minLength={10} maxLength={600} required placeholder={tr("What went wrong? Describe the problem.")} aria-label={tr("What went wrong? Describe the problem.")}
                    value={text[w.id] ?? ""} onChange={(e) => setText((t) => ({ ...t, [w.id]: e.target.value }))} />
                  <Button type="submit" size="sm" variant="outline" className="w-fit">{tr("Make a warranty claim")}</Button>
                </form>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
