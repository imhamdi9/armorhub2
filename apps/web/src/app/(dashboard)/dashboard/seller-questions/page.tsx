"use client";

import { useEffect, useState } from "react";

import { Note } from "@/components/installations/shared";
import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { LoadingBlock } from "@/components/ui/loading-block";
import { errorMessage } from "@/lib/errors";
import { formatDate, tr } from "@/lib/i18n";
import { answerQuestion, getQuestionInbox, type ProductQuestionRow } from "@/services/platform.service";

/** Questions shoppers asked about this seller's products. An answer appears on the product page and the shopper is told. */
export default function SellerQuestionsPage() {
  const [rows, setRows] = useState<ProductQuestionRow[] | null>(null);
  const [drafts, setDrafts] = useState<Record<number, string>>({});
  const [err, setErr] = useState<string | null>(null);
  const load = () => getQuestionInbox().then(setRows).catch(() => setRows([]));
  useEffect(() => { load(); }, []);

  async function send(id: number) {
    setErr(null);
    try { await answerQuestion(id, drafts[id] ?? ""); setDrafts((d) => ({ ...d, [id]: "" })); await load(); } catch (e) { setErr(errorMessage(e)); }
  }

  return (
    <div className="space-y-4">
      <PageHeader title={tr("Customer questions")} />
      {err && <Note tone="warn">{err}</Note>}
      {rows === null ? <LoadingBlock /> : rows.length === 0 ? <EmptyState>{tr("No questions yet.")}</EmptyState> : rows.map((q) => (
        <section key={q.id} className="rounded-xl border p-4" data-testid="seller-question">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <p className="font-semibold">{q.product?.name}</p>
            <Badge variant={q.answer ? "secondary" : "default"}>{q.answer ? tr("Answered") : tr("Needs an answer")}</Badge>
          </div>
          <p className="mt-2 text-sm">“{q.question}” <span className="text-muted-foreground">· {q.asker}{q.asked_at ? ` · ${formatDate(q.asked_at)}` : ""}</span></p>
          {q.answer ? (
            <p className="mt-2 rounded-lg bg-muted p-3 text-sm">{q.answer}</p>
          ) : (
            <div className="mt-3 flex flex-wrap gap-2">
              <textarea className="min-w-64 flex-1 rounded-lg border p-2 text-sm" rows={2} maxLength={1000} placeholder={tr("Write your answer")} aria-label={tr("Write your answer")}
                value={drafts[q.id] ?? ""} onChange={(e) => setDrafts((d) => ({ ...d, [q.id]: e.target.value }))} />
              <Button size="sm" disabled={(drafts[q.id] ?? "").trim().length < 2} onClick={() => send(q.id)}>{tr("Send answer")}</Button>
            </div>
          )}
        </section>
      ))}
    </div>
  );
}
