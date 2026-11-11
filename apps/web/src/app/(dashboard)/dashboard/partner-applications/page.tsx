"use client";

import { useEffect, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { errorMessage } from "@/lib/errors";
import { formatDateTime, tr } from "@/lib/i18n";
import { approvePartnerApplication, getPartnerApplications, rejectPartnerApplication } from "@/services/platform.service";
import type { PartnerApplication } from "@/types/platform";

const TABS = [["pending", "Pending"], ["approved", "Approved"], ["rejected", "Rejected"]] as const;

/** Businesses that applied to become partners. Approving creates their account and gives the partner role at once. */
export default function PartnerApplicationsPage() {
  const [status, setStatus] = useState<string>("pending");
  const [items, setItems] = useState<PartnerApplication[] | null>(null);
  const [forbidden, setForbidden] = useState(false);
  const [reasons, setReasons] = useState<Record<number, string>>({});
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState<number | null>(null);

  const [tick, setTick] = useState(0);

  useEffect(() => {
    getPartnerApplications(status).then((r) => setItems(r.data)).catch(() => setForbidden(true));
  }, [status, tick]);

  async function decide(id: number, action: "approve" | "reject") {
    setBusy(id);
    setError(null);
    try {
      if (action === "approve") await approvePartnerApplication(id);
      else await rejectPartnerApplication(id, reasons[id] ?? "");
      setTick((t) => t + 1);
    } catch (e) {
      setError(errorMessage(e));
    } finally {
      setBusy(null);
    }
  }

  if (forbidden) return <EmptyState>{tr("You do not have access to partner applications.")}</EmptyState>;

  return (
    <div className="max-w-3xl">
      <PageHeader title={tr("Partner applications")} />
      <p className="mb-4 text-sm text-muted-foreground">{tr("Approving creates the partner's account and gives them the partner role immediately. They sign in with the password they chose.")}</p>

      <div role="tablist" className="mb-6 inline-flex gap-1 rounded-full bg-secondary p-1">
        {TABS.map(([key, label]) => (
          <button key={key} role="tab" aria-selected={status === key} type="button" onClick={() => { setItems(null); setStatus(key); }}
            className={`rounded-full px-4 py-1.5 text-sm font-semibold ${status === key ? "bg-lime text-forest" : "text-secondary-foreground"}`}>{tr(label)}</button>
        ))}
      </div>
      {error && <p className="mb-4 text-sm text-red-500" role="alert">{error}</p>}

      {items === null ? (
        <p className="text-muted-foreground">{tr("Loading...")}</p>
      ) : items.length === 0 ? (
        <EmptyState>{tr("No applications here.")}</EmptyState>
      ) : (
        <div className="space-y-4">
          {items.map((a) => (
            <article key={a.id} className="rounded-xl border p-5">
              <div className="flex flex-wrap items-start justify-between gap-2">
                <div>
                  <h2 className="text-lg font-bold">{a.business_name}</h2>
                  <p className="text-sm text-muted-foreground">{a.store?.name} · {[a.city, a.country].filter(Boolean).join(", ")}</p>
                </div>
                <Badge variant={a.status === "approved" ? "default" : "secondary"}>{tr(a.status === "pending" ? "Pending" : a.status === "approved" ? "Approved" : "Rejected")}</Badge>
              </div>
              <dl className="mt-3 grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                <div><dt className="inline text-muted-foreground">{tr("Contact")}: </dt><dd className="inline">{a.contact_name}</dd></div>
                <div><dt className="inline text-muted-foreground">{tr("Email")}: </dt><dd className="inline" dir="ltr">{a.email}</dd></div>
                <div><dt className="inline text-muted-foreground">{tr("Mobile number")}: </dt><dd className="inline" dir="ltr">{a.phone}</dd></div>
                <div><dt className="inline text-muted-foreground">{tr("Applied")}: </dt><dd className="inline">{formatDateTime(a.created_at)}</dd></div>
              </dl>
              {a.description && <p className="mt-3 whitespace-pre-line text-sm">{a.description}</p>}
              {a.rejection_reason && <p className="mt-3 text-sm text-muted-foreground">{tr("Reason")}: {a.rejection_reason}</p>}

              {a.status === "pending" && (
                <div className="mt-4 flex flex-wrap items-center gap-2">
                  <Button disabled={busy === a.id} onClick={() => decide(a.id, "approve")}>{tr("Approve")}</Button>
                  <Input className="w-64" placeholder={tr("Reason (optional)")} aria-label={tr("Reason (optional)")} maxLength={300} value={reasons[a.id] ?? ""} onChange={(e) => setReasons((r) => ({ ...r, [a.id]: e.target.value }))} />
                  <Button variant="outline" disabled={busy === a.id} onClick={() => decide(a.id, "reject")}>{tr("Reject")}</Button>
                </div>
              )}
            </article>
          ))}
        </div>
      )}
    </div>
  );
}
