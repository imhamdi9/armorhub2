"use client";

import { useEffect, useState } from "react";

import { PageHeader } from "@/components/layout/page-header";
import { LoadingBlock } from "@/components/ui/loading-block";
import { formatDateTime, tr } from "@/lib/i18n";
import { getOutbox, getOutboxMail, type OutboxMail, type OutboxRow } from "@/services/ops.service";

/** The e-mails the platform sent (kept here while the "outbox" mailer is on): what each person would have received, in their language. */
export default function EmailsPage() {
  const [rows, setRows] = useState<OutboxRow[] | null>(null);
  const [open, setOpen] = useState<OutboxMail | null>(null);
  useEffect(() => { getOutbox().then(setRows).catch(() => setRows([])); }, []);

  return (
    <div className="space-y-4">
      <PageHeader title={tr("Sent e-mails")} />
      {rows === null ? <LoadingBlock /> : rows.length === 0 ? <p className="text-sm text-muted-foreground">{tr("Nothing was sent yet.")}</p> : (
        <div className="grid gap-4 lg:grid-cols-[22rem_1fr]">
          <ul className="max-h-[32rem] divide-y overflow-auto rounded-xl border" data-testid="outbox">
            {rows.map((r) => (
              <li key={r.id}>
                <button type="button" onClick={() => getOutboxMail(r.id).then(setOpen)} className={`w-full p-3 text-start text-sm hover:bg-muted ${open?.id === r.id ? "bg-muted" : ""}`}>
                  <b className="block truncate">{r.subject}</b>
                  <span className="block truncate text-muted-foreground">{tr("To")}: {r.to}</span>
                  <span className="text-xs text-muted-foreground">{formatDateTime(r.created_at)}</span>
                </button>
              </li>
            ))}
          </ul>
          <section className="min-h-64 rounded-xl border p-3" data-testid="mail-view">
            {open ? (
              <>
                <p className="mb-2 text-sm"><b>{open.subject}</b> · {open.to}</p>
                {/* The e-mail's own HTML, with every script and outside access switched off. */}
                <iframe title={open.subject} sandbox="" srcDoc={open.html ?? `<pre>${open.text ?? ""}</pre>`} className="h-[28rem] w-full rounded-lg border bg-white" />
              </>
            ) : <p className="text-sm text-muted-foreground">{tr("Choose an e-mail to read it.")}</p>}
          </section>
        </div>
      )}
    </div>
  );
}
