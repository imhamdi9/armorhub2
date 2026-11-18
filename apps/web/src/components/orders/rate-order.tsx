"use client";

import { useEffect, useState } from "react";

import { Note } from "@/components/installations/shared";
import { Button } from "@/components/ui/button";
import { errorMessage } from "@/lib/errors";
import { tr } from "@/lib/i18n";
import { getMyReview, saveReview } from "@/services/platform.service";

/** Asked right on the delivered order: how was it? Uses the same review as the product page, so one rating counts once. */
export function RateOrder({ productId }: { productId: number }) {
  const [state, setState] = useState<"loading" | "hidden" | "ready" | "saved">("loading");
  const [rating, setRating] = useState(0);
  const [comment, setComment] = useState("");
  const [err, setErr] = useState<string | null>(null);

  useEffect(() => {
    getMyReview(productId).then((r) => {
      if (!r.eligible) return setState("hidden");
      if (r.review) { setRating(r.review.rating); setComment(r.review.comment ?? ""); }
      setState("ready");
    }).catch(() => setState("hidden"));
  }, [productId]);

  if (state === "loading" || state === "hidden") return null;

  return (
    <form
      className="mb-6 rounded-xl border p-4"
      data-testid="rate-order"
      onSubmit={async (e) => {
        e.preventDefault();
        setErr(null);
        if (rating < 1) return setErr(tr("Choose a rating from 1 to 5."));
        try { await saveReview(productId, rating, comment); setState("saved"); } catch (x) { setErr(errorMessage(x)); }
      }}
    >
      <p className="font-semibold">{tr("You received this order. How was it?")}</p>
      <div className="my-2 flex gap-1" role="radiogroup" aria-label={tr("Rating")}>
        {[1, 2, 3, 4, 5].map((n) => (
          <button key={n} type="button" role="radio" aria-checked={rating === n} aria-label={`${n}`} onClick={() => setRating(n)} className={`text-3xl leading-none ${n <= rating ? "text-amber-500" : "text-muted-foreground/40"}`}>★</button>
        ))}
      </div>
      <textarea value={comment} onChange={(e) => setComment(e.target.value)} maxLength={1000} rows={2} className="mb-2 w-full rounded-[10px] border p-2 text-sm" placeholder={tr("Add a comment (optional)")} />
      {err && <Note tone="warn">{err}</Note>}
      {state === "saved" && <Note tone="ok">{tr("Thank you. Your review is saved.")}</Note>}
      <Button type="submit" size="sm">{tr("Save review")}</Button>
    </form>
  );
}
