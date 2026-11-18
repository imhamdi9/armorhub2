"use client";

import Link from "next/link";
import { useEffect, useState } from "react";

import { field, Note } from "@/components/installations/shared";
import { PageHeader } from "@/components/layout/page-header";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { LoadingBlock } from "@/components/ui/loading-block";
import { errorMessage } from "@/lib/errors";
import { formatDateTime, tr } from "@/lib/i18n";
import { closeSupportTicket, getSupportTickets, sendSupportTicket, type SupportTicket } from "@/services/ops.service";

/** Asking ArmorHub for help and reading the answer. Questions about one order belong in that order's messages. */
export default function HelpPage() {
  const [rows, setRows] = useState<SupportTicket[] | null>(null);
  const [err, setErr] = useState<string | null>(null);
  const [sent, setSent] = useState(false);
  const load = () => getSupportTickets().then(setRows).catch(() => setRows([]));
  useEffect(() => { load(); }, []);

  return (
    <div className="space-y-8">
      <PageHeader title={tr("Help")} />
      <p className="text-sm text-muted-foreground">
        {tr("Ask us anything. For a question about one order, use the messages on that order, so the seller can answer too.")}{" "}
        <Link href="/#faq" className="underline">{tr("Read the FAQ")}</Link>
      </p>

      <section className="rounded-xl border p-4" data-testid="help-form">
        <h2 className="mb-3 text-lg font-semibold">{tr("Ask a question")}</h2>
        {err && <Note tone="warn">{err}</Note>}
        {sent && <Note tone="ok">{tr("We received your question. We will answer here and tell you.")}</Note>}
        <form
          className="grid gap-3"
          onSubmit={async (e) => {
            e.preventDefault();
            setErr(null);
            setSent(false);
            const form = e.currentTarget;
            const f = new FormData(form);
            try { await sendSupportTicket(String(f.get("subject")), String(f.get("body"))); form.reset(); setSent(true); await load(); } catch (x) { setErr(errorMessage(x)); }
          }}
        >
          <input name="subject" className={field} placeholder={tr("Subject")} required minLength={3} maxLength={120} />
          <textarea name="body" className="min-h-28 rounded-[10px] border p-2 text-sm" placeholder={tr("Tell us what happened")} required minLength={5} maxLength={2000} />
          <div><Button type="submit">{tr("Send")}</Button></div>
        </form>
      </section>

      <section>
        <h2 className="mb-2 text-lg font-semibold">{tr("Your questions")}</h2>
        {rows === null ? <LoadingBlock /> : rows.length === 0 ? <p className="text-sm text-muted-foreground">{tr("No questions yet.")}</p> : (
          <ul className="space-y-3" data-testid="tickets">
            {rows.map((t) => (
              <li key={t.id} className="rounded-xl border p-4 text-sm">
                <div className="mb-1 flex flex-wrap items-center justify-between gap-2">
                  <b>{t.subject}</b>
                  <span className="flex items-center gap-2">
                    <Badge variant={t.status === "answered" ? "default" : "secondary"}>{tr(t.status === "open" ? "Waiting for an answer" : t.status === "answered" ? "Answered" : "Closed")}</Badge>
                    {t.status === "answered" && <Button size="sm" variant="outline" onClick={() => closeSupportTicket(t.id).then(load)}>{tr("Close")}</Button>}
                  </span>
                </div>
                <p className="whitespace-pre-wrap text-muted-foreground">{t.body}</p>
                <p className="text-xs text-muted-foreground">{formatDateTime(t.created_at)}</p>
                {t.reply && <div className="mt-2 rounded-lg bg-primary/10 p-3"><p className="mb-1 text-xs font-semibold">ArmorHub</p><p className="whitespace-pre-wrap">{t.reply}</p></div>}
              </li>
            ))}
          </ul>
        )}
      </section>
    </div>
  );
}
