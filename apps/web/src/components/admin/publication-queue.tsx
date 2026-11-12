"use client";

import { useCallback, useEffect, useState } from "react";

import { useCurrentUser } from "@/components/providers/current-user";
import { Button } from "@/components/ui/button";
import { errorMessage } from "@/lib/errors";
import { formatDateTime, tr } from "@/lib/i18n";
import { decidePublication, getPublications, type Publication } from "@/services/admin.service";

/** Content changes waiting for approval or for their publish time. Someone else must approve your change. */
export function PublicationQueue({ refreshKey = 0 }: { refreshKey?: number }) {
  const me = useCurrentUser();
  const [open, setOpen] = useState<Publication[]>([]);
  const [recent, setRecent] = useState<Publication[]>([]);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    const r = await getPublications();
    setOpen(r.open);
    setRecent(r.recent);
  }, []);

  useEffect(() => {
    let live = true;
    getPublications().then((r) => { if (live) { setOpen(r.open); setRecent(r.recent); } }).catch(() => undefined);

    return () => { live = false; };
  }, [refreshKey]);

  async function decide(id: number, action: "approve" | "reject" | "cancel") {
    setError(null);
    try {
      await decidePublication(id, action);
      await load();
    } catch (e) {
      setError(errorMessage(e));
    }
  }

  if (open.length === 0 && recent.length === 0) return null;

  return (
    <section className="mt-8 rounded-xl border p-5" aria-label={tr("Approvals and schedule")}>
      <h2 className="mb-3 font-bold">{tr("Approvals and schedule")}</h2>
      {error && <p className="mb-2 text-sm text-red-500" role="alert">{error}</p>}
      {open.length === 0 ? <p className="text-sm text-muted-foreground">{tr("Nothing is waiting.")}</p> : (
        <ul className="space-y-2">
          {open.map((p) => (
            <li key={p.id} className="flex flex-wrap items-center justify-between gap-2 rounded-lg border px-3 py-2 text-sm">
              <span>
                <strong>{tr(p.surface_label)}</strong> ({p.locale}) · {p.status === "pending" ? tr("waiting for approval") : tr("scheduled")}
                {p.publish_at ? ` · ${formatDateTime(p.publish_at)}` : ""}{p.requested_by_name ? ` · ${p.requested_by_name}` : ""}{p.note ? ` · ${p.note}` : ""}
              </span>
              <span className="flex gap-2">
                {p.status === "pending" && p.requested_by !== me?.id && <Button size="sm" onClick={() => decide(p.id, "approve")}>{tr("Approve")}</Button>}
                {p.status === "pending" && p.requested_by !== me?.id && <Button size="sm" variant="outline" onClick={() => decide(p.id, "reject")}>{tr("Reject")}</Button>}
                <Button size="sm" variant="outline" onClick={() => decide(p.id, "cancel")}>{tr("Cancel")}</Button>
              </span>
            </li>
          ))}
        </ul>
      )}
      {recent.length > 0 && (
        <details className="mt-3 text-sm">
          <summary className="cursor-pointer text-muted-foreground">{tr("Recent decisions")}</summary>
          <ul className="mt-2 space-y-1">
            {recent.map((p) => <li key={p.id}>{tr(p.surface_label)} ({p.locale}) · {tr(p.status)}</li>)}
          </ul>
        </details>
      )}
    </section>
  );
}
