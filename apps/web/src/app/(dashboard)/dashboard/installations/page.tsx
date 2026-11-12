"use client";

import Link from "next/link";
import { useEffect, useState } from "react";

import { StatusBadge } from "@/components/installations/shared";
import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { Button } from "@/components/ui/button";
import { formatDateTime, formatMoney, tr } from "@/lib/i18n";
import { getInstallations, getLoyalty, type CustomerJob } from "@/services/installation.service";

/** The customer's installation requests, and their loyalty points. */
export default function InstallationsPage() {
  const [jobs, setJobs] = useState<CustomerJob[] | null>(null);
  const [loyalty, setLoyalty] = useState<Awaited<ReturnType<typeof getLoyalty>> | null>(null);
  const [failed, setFailed] = useState(false);

  useEffect(() => {
    getInstallations().then(setJobs).catch(() => setFailed(true));
    getLoyalty().then(setLoyalty).catch(() => undefined);
  }, []);

  return (
    <div className="max-w-3xl">
      <PageHeader title="Installation requests" action={<Button render={<Link href="/dashboard/installations/new" />}>{tr("New request")}</Button>} />
      <p className="mb-4 text-sm text-muted-foreground">{tr("Ask nearby centres to fit or repair something. You compare their offers without sharing your details. You pay on the platform, and the centre is paid only after the work is proven.")}</p>

      {loyalty && (
        <p className="mb-6 rounded-xl border p-3 text-sm" data-testid="loyalty">
          {tr("Your loyalty points")}: <strong>{loyalty.points}</strong> <span className="text-muted-foreground">({tr("each point is worth")} {loyalty.point_value})</span>
        </p>
      )}

      {failed ? <EmptyState>{tr("Could not load your requests. Please try again.")}</EmptyState>
        : jobs === null ? <p className="text-muted-foreground">{tr("Loading...")}</p>
        : jobs.length === 0 ? <EmptyState>{tr("No installation requests yet.")}</EmptyState>
        : (
          <div className="space-y-3">
            {jobs.map((j) => (
              <Link key={j.id} href={`/dashboard/installations/${j.id}`} className="block rounded-lg border p-4 hover:bg-muted">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <p className="font-medium">{j.subject}</p>
                  <StatusBadge status={j.status} />
                </div>
                <p className="mt-1 text-sm text-muted-foreground">{j.parts.join(" · ")}</p>
                <p className="mt-1 text-xs text-muted-foreground">
                  {formatDateTime(j.created_at)}
                  {j.status === "offers_received" || j.status === "open" ? ` · ${j.offer_count} ${tr("offers")}` : ""}
                  {j.pay_amount ? ` · ${formatMoney(j.pay_amount, j.currency ?? "EGP")}` : ""}
                </p>
              </Link>
            ))}
          </div>
        )}
    </div>
  );
}
