"use client";

import Link from "next/link";
import { useEffect, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { Badge } from "@/components/ui/badge";
import { formatDateTime, formatNumber, tr } from "@/lib/i18n";
import { statusLabel } from "@/lib/orders";
import { getOverview } from "@/services/admin.service";
import type { Overview } from "@/types/admin";

const SEVERITY: Record<string, string> = {
  fail: "border-red-300 bg-red-50",
  warn: "border-amber-300 bg-amber-50",
  info: "border-blue-200 bg-blue-50",
};

const money = (n: number) => formatNumber(n, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

/** Everything an administrator should know at a glance. */
export default function AdminOverviewPage() {
  const [data, setData] = useState<Overview | null>(null);
  const [forbidden, setForbidden] = useState(false);

  useEffect(() => {
    getOverview().then(setData).catch(() => setForbidden(true));
  }, []);

  if (forbidden) return <EmptyState>Only platform admins can open this page.</EmptyState>;
  if (!data) return <p className="text-muted-foreground">{tr("Loading...")}</p>;

  return (
    <div>
      <PageHeader title="Administration" />

      <section className="mb-8">
        <h2 className="mb-3 text-xl font-semibold">{tr("Needs attention")}</h2>
        {data.attention.length === 0 ? (
          <p className="rounded-lg border border-green-300 bg-green-50 p-4 text-sm">{tr("All clear. Nothing needs your attention right now.")}</p>
        ) : (
          <div className="space-y-2">
            {data.attention.map((a) => (
              <Link key={a.key} href={a.path} className={`flex items-center justify-between rounded-lg border p-3 hover:opacity-80 ${SEVERITY[a.severity]}`}>
                <span>{tr(a.label)}</span>
                <strong>{formatNumber(a.count)}</strong>
              </Link>
            ))}
          </div>
        )}
      </section>

      <section className="mb-8 grid grid-cols-2 gap-3 md:grid-cols-4">
        {data.numbers.map((c) => (
          <div key={c.key} className="rounded-lg border p-4">
            <p className="text-sm text-muted-foreground">{tr(c.label)}</p>
            <p className="text-2xl font-bold">{c.format === "money" ? money(c.value) : formatNumber(c.value)}</p>
          </div>
        ))}
      </section>

      <section className="mb-8 grid gap-4 md:grid-cols-2">
        <div className="rounded-lg border p-4">
          <h2 className="mb-2 font-semibold">{tr("Payments")}</h2>
          <p className="text-sm">
            {tr("Active provider")}: <strong>{tr(data.payments.provider)}</strong>{" "}
            <Badge variant={data.payments.configured ? "default" : "outline"}>
              {data.payments.configured ? tr("Configured") : tr("Not configured")}
            </Badge>
          </p>
          <Link href="/dashboard/admin/payments" className="mt-2 inline-block text-sm underline">{tr("Manage payment providers")}</Link>
        </div>

        <div className="rounded-lg border p-4">
          <h2 className="mb-2 font-semibold">{tr("Orders by status")}</h2>
          <div className="flex flex-wrap gap-2">
            {Object.keys(data.orders_by_status).length === 0 && <span className="text-sm text-muted-foreground">—</span>}
            {Object.entries(data.orders_by_status).map(([status, n]) => (
              <span key={status} className="rounded-full border px-3 py-1 text-sm">{statusLabel(status)}: <strong>{n}</strong></span>
            ))}
          </div>
        </div>
      </section>

      <section>
        <div className="mb-3 flex items-center justify-between">
          <h2 className="text-xl font-semibold">{tr("Recent activity")}</h2>
          <Link href="/dashboard/admin/audit" className="text-sm underline">{tr("View audit log")}</Link>
        </div>
        <div className="divide-y rounded-lg border">
          {data.recent_activity.map((e) => (
            <div key={e.id} className="flex flex-wrap items-center justify-between gap-2 p-3 text-sm">
              <span><code className="rounded bg-muted px-1">{e.action}</code> {e.subject ?? ""}</span>
              <span className="text-muted-foreground">{e.user ?? tr("System")} · {formatDateTime(e.created_at)}</span>
            </div>
          ))}
        </div>
      </section>
    </div>
  );
}
