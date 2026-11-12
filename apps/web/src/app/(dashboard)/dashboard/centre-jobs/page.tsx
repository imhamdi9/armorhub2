"use client";

import Link from "next/link";
import { useEffect, useState } from "react";

import { StatusBadge } from "@/components/installations/shared";
import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { formatMoney, tr } from "@/lib/i18n";
import { getCentreJobs, type CentreJob } from "@/services/installation.service";

export default function CentreJobsPage() {
  const [rows, setRows] = useState<CentreJob[] | null>(null);
  const [failed, setFailed] = useState(false);
  useEffect(() => { getCentreJobs().then(setRows).catch(() => setFailed(true)); }, []);

  return (
    <div className="max-w-3xl">
      <PageHeader title="Installation jobs" />
      {failed ? <EmptyState>{tr("Could not load jobs.")}</EmptyState> : rows === null ? <p className="text-muted-foreground">{tr("Loading...")}</p>
        : rows.length === 0 ? <EmptyState>{tr("No accepted jobs yet.")}</EmptyState>
        : (
          <div className="space-y-3">
            {rows.map((j) => (
              <Link key={j.id} href={`/dashboard/centre-jobs/${j.id}`} className="block rounded-lg border p-4 hover:bg-muted">
                <div className="flex flex-wrap items-center justify-between gap-2"><p className="font-medium">{j.subject}</p><StatusBadge status={j.status} side="centre" /></div>
                <p className="mt-1 text-sm text-muted-foreground">{j.parts.join(" · ")} · {tr("you earn")} {formatMoney(j.you_earn, j.currency ?? "EGP")}</p>
              </Link>
            ))}
          </div>
        )}
    </div>
  );
}
