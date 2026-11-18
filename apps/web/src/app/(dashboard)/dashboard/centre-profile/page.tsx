"use client";

import { useEffect, useState } from "react";

import { Note } from "@/components/installations/shared";
import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { LoadingBlock } from "@/components/ui/loading-block";
import { errorMessage } from "@/lib/errors";
import { tr } from "@/lib/i18n";
import { getCentreReviews, getPriceList, replyToReview, savePriceList, type CentreReview, type PriceItem } from "@/services/installation.service";

/** A centre's usual prices (shown beside its offers) and the reviews customers left, which it may answer once, publicly. */
export default function CentreProfilePage() {
  const [items, setItems] = useState<PriceItem[] | null>(null);
  const [reviews, setReviews] = useState<CentreReview[]>([]);
  const [replies, setReplies] = useState<Record<number, string>>({});
  const [msg, setMsg] = useState<{ ok: boolean; text: string } | null>(null);
  const loadReviews = () => getCentreReviews().then(setReviews).catch(() => setReviews([]));
  useEffect(() => { getPriceList().then(setItems).catch(() => setItems([])); loadReviews(); }, []);
  if (!items) return <LoadingBlock />;
  const setRow = (i: number, patch: Partial<PriceItem>) => setItems(items.map((r, k) => (k === i ? { ...r, ...patch } : r)));

  return (
    <div className="max-w-3xl space-y-8">
      <PageHeader title={tr("Prices and reviews")} />
      {msg && <Note tone={msg.ok ? "ok" : "warn"}>{msg.text}</Note>}

      <section data-testid="price-list">
        <h2 className="mb-1 text-lg font-semibold">{tr("Your usual prices")}</h2>
        <p className="mb-3 text-sm text-muted-foreground">{tr("Customers see these next to your offers. Do not put phone numbers or links here.")}</p>
        <form className="space-y-2" onSubmit={async (e) => {
          e.preventDefault(); setMsg(null);
          try { setItems(await savePriceList(items)); setMsg({ ok: true, text: tr("Saved") }); } catch (x) { setMsg({ ok: false, text: errorMessage(x) }); }
        }}>
          {items.map((r, i) => (
            <div key={i} className="grid gap-2 sm:grid-cols-[1fr_8rem_1fr_auto]">
              <Input aria-label={tr("Service")} placeholder={tr("Service")} value={r.service} required minLength={2} maxLength={80} onChange={(e) => setRow(i, { service: e.target.value })} />
              <Input aria-label={tr("Price")} type="number" min={0} step="0.01" placeholder={tr("Price")} value={r.price} required onChange={(e) => setRow(i, { price: Number(e.target.value) })} />
              <Input aria-label={tr("Note")} placeholder={tr("Note")} value={r.note ?? ""} maxLength={120} onChange={(e) => setRow(i, { note: e.target.value })} />
              <Button type="button" variant="outline" size="sm" onClick={() => setItems(items.filter((_, k) => k !== i))}>{tr("Remove")}</Button>
            </div>
          ))}
          <div className="flex gap-2">
            {items.length < 20 && <Button type="button" variant="outline" onClick={() => setItems([...items, { service: "", price: 0, note: null }])}>{tr("Add a service")}</Button>}
            <Button type="submit">{tr("Save")}</Button>
          </div>
        </form>
      </section>

      <section data-testid="centre-reviews">
        <h2 className="mb-2 text-lg font-semibold">{tr("Customer reviews")}</h2>
        {reviews.length === 0 ? <EmptyState>{tr("No reviews yet.")}</EmptyState> : (
          <ul className="space-y-3">
            {reviews.map((r) => (
              <li key={r.id} className="rounded-xl border p-3 text-sm">
                <p className="font-medium">{"★".repeat(r.rating)}{"☆".repeat(5 - r.rating)}</p>
                {r.comment && <p className="mt-1">{r.comment}</p>}
                {r.reply ? <p className="mt-2 rounded-lg bg-muted p-2">{tr("Your answer")}: {r.reply}</p> : (
                  <form className="mt-2 flex gap-2" onSubmit={async (e) => {
                    e.preventDefault(); setMsg(null);
                    try { await replyToReview(r.id, replies[r.id] ?? ""); setReplies({ ...replies, [r.id]: "" }); await loadReviews(); } catch (x) { setMsg({ ok: false, text: errorMessage(x) }); }
                  }}>
                    <Input aria-label={tr("Answer this review")} placeholder={tr("Answer this review")} value={replies[r.id] ?? ""} maxLength={500} required minLength={2} onChange={(e) => setReplies({ ...replies, [r.id]: e.target.value })} />
                    <Button type="submit" size="sm">{tr("Send")}</Button>
                  </form>
                )}
              </li>
            ))}
          </ul>
        )}
      </section>
    </div>
  );
}
