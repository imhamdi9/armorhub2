"use client";

import { useEffect, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { Button } from "@/components/ui/button";
import { errorMessage } from "@/lib/errors";
import { formatNumber, tr } from "@/lib/i18n";
import { getSecurity, revokeAllSessions } from "@/services/admin.service";
import type { SecurityReport } from "@/types/admin";

const STATUS: Record<string, { dot: string; label: string }> = {
  ok: { dot: "bg-green-500", label: "OK" },
  warn: { dot: "bg-amber-500", label: "Warning" },
  fail: { dot: "bg-red-500", label: "Problem" },
};

export default function AdminSecurityPage() {
  const [report, setReport] = useState<SecurityReport | null>(null);
  const [forbidden, setForbidden] = useState(false);
  const [message, setMessage] = useState<{ ok: boolean; text: string } | null>(null);
  const [tick, setTick] = useState(0);

  useEffect(() => {
    getSecurity().then(setReport).catch(() => setForbidden(true));
  }, [tick]);

  async function signEveryoneOut() {
    if (!window.confirm(tr("Sign everyone else out? All other users must sign in again."))) return;
    try {
      const r = await revokeAllSessions();
      setMessage({ ok: true, text: tr(r.message) });
      setTick((t) => t + 1);
    } catch (e) {
      setMessage({ ok: false, text: errorMessage(e) });
    }
  }

  if (forbidden) return <EmptyState>Only platform admins can open this page.</EmptyState>;
  if (!report) return <p className="text-muted-foreground">{tr("Loading...")}</p>;

  const last = report.last_24h;

  return (
    <div className="max-w-3xl">
      <PageHeader title="Security" />

      <section className="mb-8">
        <h2 className="mb-3 text-xl font-semibold">{tr("Health checks")}</h2>
        <ul className="divide-y rounded-lg border">
          {report.checks.map((c) => (
            <li key={c.id} className="flex items-start gap-3 p-3 text-sm">
              <span className={`mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full ${STATUS[c.status].dot}`} aria-label={STATUS[c.status].label} />
              <span>{tr(c.message)}</span>
            </li>
          ))}
        </ul>
        <p className="mt-2 text-xs text-muted-foreground">{tr("Tune limits, email rules and the bot challenge under Settings > Security.")}</p>
      </section>

      <section className="mb-8">
        <h2 className="mb-3 text-xl font-semibold">{tr("Last 24 hours")}</h2>
        <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
          {([
            ["Failed sign-ins", last.failed_logins],
            ["Blocked signups", last.blocked_signups],
            ["Blocked reset requests", last.blocked_resets],
            ["New accounts", last.new_accounts],
          ] as const).map(([label, n]) => (
            <div key={label} className="rounded-lg border p-4">
              <p className="text-sm text-muted-foreground">{tr(label)}</p>
              <p className="text-2xl font-bold">{formatNumber(n)}</p>
            </div>
          ))}
        </div>

        {report.top_offenders.length > 0 && (
          <div className="mt-4 rounded-lg border p-4">
            <h3 className="mb-2 font-semibold">{tr("Addresses with the most failed sign-ins")}</h3>
            <ul className="space-y-1 text-sm">
              {report.top_offenders.map((o) => (
                <li key={o.ip} className="flex justify-between"><code>{o.ip}</code><span>{formatNumber(Number(o.attempts))}</span></li>
              ))}
            </ul>
          </div>
        )}
      </section>

      <section className="rounded-lg border border-red-200 p-4">
        <h2 className="mb-1 text-lg font-semibold">{tr("Emergency")}</h2>
        <p className="mb-3 text-sm text-muted-foreground">
          {tr("Active sessions")}: {formatNumber(report.active_sessions)}. {tr("If you suspect an attack, sign every other user out. Their accounts stay intact; they only have to sign in again.")}
        </p>
        <Button variant="outline" onClick={signEveryoneOut}>{tr("Sign everyone else out")}</Button>
        {message && <p className={`mt-2 text-sm ${message.ok ? "text-green-600" : "text-red-500"}`}>{message.text}</p>}
      </section>
    </div>
  );
}
