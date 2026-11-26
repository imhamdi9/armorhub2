"use client";

import { useCallback, useEffect, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { LoadingBlock } from "@/components/ui/loading-block";
import { errorMessage, loadFailure } from "@/lib/errors";
import { formatDateTime, tr } from "@/lib/i18n";
import { getSystem, removeFailedJob, retryFailedJob, type SystemReport } from "@/services/admin.service";

const TONE: Record<string, string> = {
  ok: "border-green-300 bg-green-50",
  warn: "border-amber-300 bg-amber-50",
  fail: "border-red-300 bg-red-50",
};
const WORD: Record<string, string> = { ok: "Working", warn: "Needs a look", fail: "Not working" };

/** Whether the machinery behind the platform runs: the scheduler, background jobs, the database, the disk. */
export default function SystemPage() {
  const [data, setData] = useState<SystemReport | null>(null);
  const [forbidden, setForbidden] = useState(false);
  const [failure, setFailure] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => { getSystem().then(setData).catch((e) => { const f = loadFailure(e); if (f.forbidden) setForbidden(true); else setFailure(f.message); }); }, []);
  useEffect(load, [load]);

  async function act(action: () => Promise<unknown>) {
    setError(null);
    try { await action(); load(); } catch (err) { setError(errorMessage(err)); }
  }

  if (forbidden) return <EmptyState>Only platform admins can open this page.</EmptyState>;
  if (failure) return <EmptyState>{failure}</EmptyState>;
  if (!data) return <LoadingBlock />;

  return (
    <div>
      <PageHeader title="System" action={<Button variant="outline" onClick={load}>{tr("Check again")}</Button>} />
      <p className="mb-4 text-sm text-muted-foreground">{tr("Whether the things that run by themselves are running: reminders, payouts, e-mails and ad conversions all depend on them.")}</p>

      <section className="mb-8 grid gap-3 md:grid-cols-2" aria-label={tr("Checks")}>
        {data.checks.map((c) => (
          <div key={c.key} className={`rounded-lg border p-4 ${TONE[c.status]}`} data-testid={`check-${c.key}`}>
            <div className="flex items-center justify-between">
              <h2 className="font-semibold">{tr(c.label)}</h2>
              <Badge variant={c.status === "ok" ? "default" : "outline"}>{tr(WORD[c.status])}</Badge>
            </div>
            <p className="mt-1 text-sm">{tr(c.detail, Object.fromEntries(Object.entries(c.params ?? {}).map(([k, v]) => [k, v ?? ""])))}</p>
          </div>
        ))}
      </section>

      <section>
        <h2 className="mb-3 text-xl font-semibold">{tr("Background jobs that failed")}</h2>
        {error && <p className="mb-3 text-sm text-red-500" role="alert">{error}</p>}
        {data.failed_jobs.length === 0 ? (
          <p className="rounded-lg border border-green-300 bg-green-50 p-4 text-sm">{tr("No failed jobs.")}</p>
        ) : (
          <div className="space-y-2">
            {data.failed_jobs.map((j) => (
              <div key={j.uuid} className="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-3">
                <div className="min-w-0">
                  <p className="font-medium" dir="ltr">{j.job}</p>
                  <p className="truncate text-sm text-muted-foreground" dir="ltr">{j.error}</p>
                  <p className="text-xs text-muted-foreground">{j.queue} · {formatDateTime(j.failed_at)}</p>
                </div>
                <div className="flex gap-2">
                  <Button size="sm" onClick={() => act(() => retryFailedJob(j.uuid))}>{tr("Try again")}</Button>
                  <Button size="sm" variant="outline" onClick={() => act(() => removeFailedJob(j.uuid))}>{tr("Remove")}</Button>
                </div>
              </div>
            ))}
          </div>
        )}
      </section>

      <p className="mt-8 text-xs text-muted-foreground" dir="ltr">PHP {data.runtime.php} · {data.runtime.environment} · queue: {data.runtime.queue} · mail: {data.runtime.mail}</p>
    </div>
  );
}
