"use client";

import { useEffect, useState } from "react";

import { useCurrentUser } from "@/components/providers/current-user";
import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { errorMessage } from "@/lib/errors";
import { formatDateTime, tr } from "@/lib/i18n";
import { hasRole } from "@/lib/navigation";
import { applicationDocumentUrl, approvePartnerApplication, getPartnerApplications, rejectPartnerApplication } from "@/services/platform.service";
import type { PartnerApplication } from "@/types/platform";

const STATUSES = [["pending", "Pending"], ["approved", "Approved"], ["rejected", "Rejected"]] as const;
const KINDS = [["", "All"], ["partner", "Partners"], ["seller", "Sellers"]] as const;
const DOC_LABELS: Record<string, string> = {
  id: "Owner ID or passport",
  commercial_registration: "Commercial registration",
  tax_certificate: "Tax / VAT certificate",
  license: "Licence or certificate",
};
const kb = (bytes: number) => `${Math.max(1, Math.round(bytes / 1024))} KB`;

/**
 * Businesses that applied to become partners or sellers. A reviewer opens each document, confirms they checked them,
 * and only then approves: approving creates the account and grants the role at once.
 */
export default function PartnerApplicationsPage() {
  const user = useCurrentUser();
  const isAdmin = hasRole(user, "platform-admin");
  const [status, setStatus] = useState<string>("pending");
  const [kind, setKind] = useState<string>("");
  const [items, setItems] = useState<PartnerApplication[] | null>(null);
  const [forbidden, setForbidden] = useState(false);
  const [reasons, setReasons] = useState<Record<number, string>>({});
  const [checked, setChecked] = useState<Record<number, boolean>>({});
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState<number | null>(null);
  const [tick, setTick] = useState(0);

  useEffect(() => {
    getPartnerApplications(status, kind || undefined).then((r) => setItems(r.data)).catch(() => setForbidden(true));
  }, [status, kind, tick]);

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

  const tab = (active: boolean) => `rounded-full px-4 py-1.5 text-sm font-semibold ${active ? "bg-lime text-forest" : "text-secondary-foreground"}`;

  return (
    <div className="max-w-3xl">
      <PageHeader title={tr("Applications")} />
      <p className="mb-4 text-sm text-muted-foreground">
        {tr("Open each document, check it, and confirm before approving. Approving creates the account and grants the role immediately; the applicant signs in with the password they chose.")}
        {!isAdmin && ` ${tr("Seller applications are reviewed by platform admins.")}`}
      </p>

      <div className="mb-3 flex flex-wrap gap-3">
        <div role="tablist" aria-label={tr("Status")} className="inline-flex gap-1 rounded-full bg-secondary p-1">
          {STATUSES.map(([key, label]) => (
            <button key={key} role="tab" aria-selected={status === key} type="button" onClick={() => { setItems(null); setStatus(key); }} className={tab(status === key)}>{tr(label)}</button>
          ))}
        </div>
        {isAdmin && (
          <div role="tablist" aria-label={tr("Type")} className="inline-flex gap-1 rounded-full bg-secondary p-1">
            {KINDS.map(([key, label]) => (
              <button key={key || "all"} role="tab" aria-selected={kind === key} type="button" onClick={() => { setItems(null); setKind(key); }} className={tab(kind === key)}>{tr(label)}</button>
            ))}
          </div>
        )}
      </div>
      {error && <p className="mb-4 text-sm text-red-500" role="alert">{error}</p>}

      {items === null ? (
        <p className="text-muted-foreground">{tr("Loading...")}</p>
      ) : items.length === 0 ? (
        <EmptyState>{tr("No applications here.")}</EmptyState>
      ) : (
        <div className="mt-3 space-y-4">
          {items.map((a) => (
            <article key={a.id} className="rounded-xl border p-5">
              <div className="flex flex-wrap items-start justify-between gap-2">
                <div>
                  <h2 className="text-lg font-bold">{a.business_name}</h2>
                  <p className="text-sm text-muted-foreground">
                    {a.type === "seller" ? `${tr("Seller")} · ${a.marketplace ?? ""}` : `${tr("Partner")} · ${a.store?.name ?? ""}`} · {[a.city, a.country].filter(Boolean).join(", ")}
                  </p>
                </div>
                <Badge variant={a.status === "approved" ? "default" : "secondary"}>{tr(a.status === "pending" ? "Pending" : a.status === "approved" ? "Approved" : "Rejected")}</Badge>
              </div>

              <dl className="mt-3 grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                <div><dt className="inline text-muted-foreground">{tr("Contact")}: </dt><dd className="inline">{a.contact_name}</dd></div>
                <div><dt className="inline text-muted-foreground">{tr("Email")}: </dt><dd className="inline" dir="ltr">{a.email}</dd></div>
                <div><dt className="inline text-muted-foreground">{tr("Mobile number")}: </dt><dd className="inline" dir="ltr">{a.phone}</dd></div>
                <div><dt className="inline text-muted-foreground">{tr("Applied")}: </dt><dd className="inline">{formatDateTime(a.created_at)}</dd></div>
                <div><dt className="inline text-muted-foreground">{tr("Commercial registration number")}: </dt><dd className="inline" dir="ltr">{a.registration_number ?? "—"}</dd></div>
                <div><dt className="inline text-muted-foreground">{tr("Tax registration number")}: </dt><dd className="inline" dir="ltr">{a.tax_number ?? "—"}</dd></div>
              </dl>
              {a.description && <p className="mt-3 whitespace-pre-line text-sm">{a.description}</p>}

              <div className="mt-4">
                <p className="text-sm font-semibold">{tr("Verification documents")}</p>
                {a.documents.length === 0 ? (
                  <p className="text-sm text-red-500">{tr("No documents on file.")}</p>
                ) : (
                  <ul className="mt-1 space-y-1 text-sm">
                    {a.documents.map((d) => (
                      <li key={d.id}>
                        {tr(DOC_LABELS[d.kind] ?? d.kind)}:{" "}
                        <a className="font-semibold text-forest underline" href={applicationDocumentUrl(a.id, d.id)} target="_blank" rel="noopener noreferrer">{d.original_name}</a>{" "}
                        <span className="text-muted-foreground">({kb(d.size)})</span>
                      </li>
                    ))}
                  </ul>
                )}
                {a.missing_documents.length > 0 && <p className="mt-1 text-sm text-red-500">{tr("Missing")}: {a.missing_documents.map((k) => tr(DOC_LABELS[k] ?? k)).join(", ")}</p>}
              </div>
              {a.rejection_reason && <p className="mt-3 text-sm text-muted-foreground">{tr("Reason")}: {a.rejection_reason}</p>}

              {a.status === "pending" && (
                <div className="mt-4 space-y-3">
                  <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" aria-label={tr("I opened and checked every document")} checked={!!checked[a.id]} onChange={(e) => setChecked((c) => ({ ...c, [a.id]: e.target.checked }))} />
                    {tr("I opened and checked every document")}
                  </label>
                  <div className="flex flex-wrap items-center gap-2">
                    <Button disabled={busy === a.id || !checked[a.id] || a.missing_documents.length > 0} onClick={() => decide(a.id, "approve")}>{tr("Approve")}</Button>
                    <Input className="w-64" placeholder={tr("Reason (optional)")} aria-label={tr("Reason (optional)")} maxLength={300} value={reasons[a.id] ?? ""} onChange={(e) => setReasons((r) => ({ ...r, [a.id]: e.target.value }))} />
                    <Button variant="outline" disabled={busy === a.id} onClick={() => decide(a.id, "reject")}>{tr("Reject")}</Button>
                  </div>
                </div>
              )}
            </article>
          ))}
        </div>
      )}
    </div>
  );
}
