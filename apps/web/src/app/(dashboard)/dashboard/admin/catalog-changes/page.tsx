"use client";

import { useEffect, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { useCurrentUser } from "@/components/providers/current-user";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { errorMessage } from "@/lib/errors";
import { tr } from "@/lib/i18n";
import { approveCatalogChange, getCatalogChanges, rejectCatalogChange, type CatalogChangeRow } from "@/services/platform.service";

/** Proposed catalog and category changes. Market admins see the state of theirs; the platform admin approves or rejects. */
export default function CatalogChangesPage() {
  const user = useCurrentUser();
  const isAdmin = !!user?.roles?.includes("platform-admin");
  const [rows, setRows] = useState<CatalogChangeRow[] | null>(null);
  const [tick, setTick] = useState(0);
  const [notes, setNotes] = useState<Record<number, string>>({});
  const [msg, setMsg] = useState<string | null>(null);

  useEffect(() => {
    let live = true;
    getCatalogChanges().then((r) => { if (live) setRows(r); }).catch(() => { if (live) setRows([]); });
    return () => { live = false; };
  }, [tick]);

  const run = async (fn: () => Promise<unknown>) => {
    setMsg(null);
    try { await fn(); setTick((t) => t + 1); } catch (e) { setMsg(errorMessage(e)); }
  };

  if (!rows) return <div className="h-24 animate-pulse rounded-md bg-muted" role="status" aria-label={tr("Loading...")} />;

  return (
    <div className="space-y-4">
      <PageHeader title="Catalog requests" />
      <p className="text-sm text-muted-foreground">{tr(isAdmin ? "Market admins propose changes to the catalogs and categories. A change goes live only when you approve it." : "Your changes to the catalogs and categories wait here until the platform admin approves them.")}</p>
      {msg && <p className="text-sm text-red-600" role="alert">{msg}</p>}
      {rows.length === 0 ? <EmptyState>{tr("No requests yet.")}</EmptyState> : (
        <ul className="divide-y rounded-xl border" data-testid="catalog-changes">
          {rows.map((c) => (
            <li key={c.id} className="flex flex-wrap items-center justify-between gap-3 p-3 text-sm">
              <div>
                <p className="font-medium">{c.summary}</p>
                <p className="text-muted-foreground">{[c.market, c.requested_by].filter(Boolean).join(" · ")}{c.note ? ` · ${c.note}` : ""}</p>
              </div>
              <div className="flex items-center gap-2">
                <Badge variant={c.status === "pending" ? "default" : "secondary"}>{tr(c.status === "pending" ? "Waiting for approval" : c.status === "approved" ? "Approved" : "Not approved")}</Badge>
                {isAdmin && c.status === "pending" && (
                  <>
                    <Button size="sm" onClick={() => run(() => approveCatalogChange(c.id))}>{tr("Approve")}</Button>
                    <Input aria-label={tr("Reason")} placeholder={tr("Reason")} className="w-40" value={notes[c.id] ?? ""} onChange={(e) => setNotes({ ...notes, [c.id]: e.target.value })} />
                    <Button size="sm" variant="outline" onClick={() => run(() => rejectCatalogChange(c.id, notes[c.id] ?? ""))}>{tr("Reject")}</Button>
                  </>
                )}
              </div>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
