"use client";

import { useEffect, useState } from "react";

import { Note } from "@/components/installations/shared";
import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { Button } from "@/components/ui/button";
import { LoadingBlock } from "@/components/ui/loading-block";
import { errorMessage } from "@/lib/errors";
import { formatMoney, tr } from "@/lib/i18n";
import { imageSrc } from "@/lib/surfaces";
import { approveProduct, getProductReviews, rejectProduct, type ReviewRow } from "@/services/platform.service";

/** The catalogue team: every product a seller submits is read here, in both languages, and approved or sent back with a reason. */
export default function ProductReviewsPage() {
  const [rows, setRows] = useState<ReviewRow[] | null>(null);
  const [notes, setNotes] = useState<Record<number, string>>({});
  const [msg, setMsg] = useState<{ ok: boolean; text: string } | null>(null);
  const load = () => getProductReviews("pending").then(setRows).catch(() => setRows([]));
  useEffect(() => { load(); }, []);
  if (!rows) return <LoadingBlock />;

  async function run(fn: () => Promise<unknown>, done: string) {
    setMsg(null);
    try { await fn(); setMsg({ ok: true, text: done }); await load(); } catch (x) { setMsg({ ok: false, text: errorMessage(x) }); }
  }

  return (
    <div className="max-w-4xl space-y-4">
      <PageHeader title={tr("Product reviews")} />
      <p className="text-sm text-muted-foreground">{tr("Sellers' products appear in the shop only after you approve them. Read the English and the Arabic copy, look at the photos, and approve or send the product back with a reason.")}</p>
      {msg && <Note tone={msg.ok ? "ok" : "warn"}>{msg.text}</Note>}
      {rows.length === 0 ? <EmptyState>{tr("No products are waiting for review.")}</EmptyState> : (
        <ul className="space-y-4" data-testid="review-list">
          {rows.map((p) => (
            <li key={p.id} className="rounded-xl border p-4" data-testid="review-row">
              <div className="flex flex-wrap items-baseline justify-between gap-2">
                <h2 className="text-lg font-semibold">{p.name} <span className="text-muted-foreground" dir="rtl">/ {p.name_ar}</span></h2>
                <span className="text-sm text-muted-foreground">{p.seller} · {formatMoney(p.price, "EGP")}</span>
              </div>
              {p.images.length > 0 && (
                <div className="mt-2 flex flex-wrap gap-2">
                  {p.images.map((i) => (
                    // eslint-disable-next-line @next/next/no-img-element -- the seller's own product photo
                    <img key={i} src={imageSrc(i) ?? ""} alt="" className="size-20 rounded-lg border object-cover" />
                  ))}
                </div>
              )}
              <div className="mt-2 grid gap-3 text-sm sm:grid-cols-2">
                <p lang="en" dir="ltr" className="rounded-lg bg-muted/50 p-2">{p.description}</p>
                <p lang="ar" dir="rtl" className="rounded-lg bg-muted/50 p-2">{p.description_ar}</p>
              </div>
              {p.fits_note && <p className="mt-2 text-sm font-bold">{p.fits_note}</p>}
              {p.bundle.length > 0 && <p className="mt-2 text-sm">{tr("Bundle")}: {p.bundle.map((b) => `${b.quantity} × ${b.name}`).join(" + ")}</p>}
              <div className="mt-3 flex flex-wrap items-center gap-2">
                <Button size="sm" onClick={() => run(() => approveProduct(p.id), tr("Approved"))}>{tr("Approve")}</Button>
                <input className="h-9 min-w-[14rem] flex-1 rounded-lg border px-2 text-sm" placeholder={tr("Why it is sent back (the seller reads this)")} maxLength={300} value={notes[p.id] ?? ""} onChange={(e) => setNotes({ ...notes, [p.id]: e.target.value })} />
                <Button size="sm" variant="outline" disabled={(notes[p.id] ?? "").trim().length < 5} onClick={() => run(() => rejectProduct(p.id, notes[p.id]), tr("Sent back to the seller"))}>{tr("Send back")}</Button>
              </div>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
