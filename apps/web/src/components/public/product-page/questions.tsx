"use client";

import Link from "next/link";
import { useEffect, useState } from "react";

import { errorMessage } from "@/lib/errors";
import { formatDate, tr } from "@/lib/i18n";
import { me } from "@/services/auth.service";
import { askQuestion, getMyQuestions, type ProductQuestionRow } from "@/services/platform.service";
import type { PublicProductPage } from "@/types/public";

/** What delivery should look like for an order placed today: a day or a short range, from the market's usual days. */
export function DeliveryEstimate({ estimate, compact = false }: { estimate?: PublicProductPage["delivery_estimate"]; compact?: boolean }) {
  if (!estimate) return null;
  const range = estimate.from === estimate.to ? formatDate(estimate.from) : `${formatDate(estimate.from)} – ${formatDate(estimate.to)}`;

  return (
    <p className={`${compact ? "" : "mt-6"} flex items-center gap-3 rounded-2xl bg-brand-wash px-5 py-4 text-sm`} data-testid="delivery-estimate">
      <span aria-hidden="true" className="text-xl">📦</span>
      <span>{tr("Order today and it arrives")} <b>{range}</b>.</span>
    </p>
  );
}

/** Answered questions, and a box to ask a new one (signed-in shoppers only). */
export function QuestionsSection({ productId, items }: { productId: number; items: NonNullable<PublicProductPage["questions"]> }) {
  const [signedIn, setSignedIn] = useState<boolean | null>(null);
  const [text, setText] = useState("");
  const [mine, setMine] = useState<ProductQuestionRow[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [sent, setSent] = useState(false);

  useEffect(() => {
    me().then(() => { setSignedIn(true); return getMyQuestions(productId).then(setMine); }).catch(() => setSignedIn(false));
  }, [productId]);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    try {
      const q = await askQuestion(productId, text);
      setMine((all) => [q, ...all]);
      setText("");
      setSent(true);
    } catch (x) {
      setError(errorMessage(x));
    }
  }

  const pending = mine.filter((q) => !q.answer);

  return (
    <section id="questions" className="mt-14 scroll-mt-24" aria-label={tr("Questions and answers")} data-testid="questions">
      <h2 className="display display-md mb-4">{tr("Questions and answers")}</h2>
      {items.length === 0 ? (
        <p className="text-sm text-slate">{tr("No questions yet. Ask the seller before you buy.")}</p>
      ) : (
        <ul className="space-y-3">
          {items.map((q) => (
            <li key={q.id} className="rounded-2xl border border-fog p-4">
              <p className="text-sm font-semibold text-obsidian"><span aria-hidden="true" className="me-2 text-brand-ink">Q</span>{q.question}</p>
              <p className="mt-2 text-sm text-charcoal"><span aria-hidden="true" className="me-2 font-semibold text-brand-ink">A</span>{q.answer}</p>
              <p className="mt-2 text-xs text-slate">{q.asker}{q.answered_at ? ` · ${formatDate(q.answered_at)}` : ""}</p>
            </li>
          ))}
        </ul>
      )}

      {signedIn === false && <p className="mt-5 text-sm"><Link className="underline" href="/login">{tr("Sign in to ask a question")}</Link></p>}
      {signedIn && (
        <form onSubmit={submit} className="mt-5 rounded-2xl border border-fog bg-brand-wash p-5" data-testid="question-form">
          <label className="font-semibold text-obsidian" htmlFor="ask">{tr("Ask the seller")}</label>
          <textarea id="ask" value={text} onChange={(e) => { setText(e.target.value); setSent(false); }} minLength={8} maxLength={300} rows={2} required
            placeholder={tr("For example: does it fit my car?")} className="mt-2 w-full rounded-xl border border-fog bg-white p-3 text-sm" />
          {error && <p className="mt-2 text-sm text-red-600" role="alert">{error}</p>}
          {sent && <p className="mt-2 text-sm text-brand-ink" role="status">{tr("Sent. The seller will answer here and you will be notified.")}</p>}
          <button type="submit" className="btn-brand mt-3 px-6 py-2.5 text-sm" disabled={text.trim().length < 8}>{tr("Send question")}</button>
          {pending.length > 0 && (
            <ul className="mt-4 space-y-1 text-sm text-slate" aria-label={tr("Waiting for an answer")}>
              {pending.map((q) => <li key={q.id}>⏳ {q.question}</li>)}
            </ul>
          )}
        </form>
      )}
    </section>
  );
}
