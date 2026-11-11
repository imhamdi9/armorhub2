"use client";

import { useEffect, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { useCurrentUser } from "@/components/providers/current-user";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { hasRole } from "@/lib/navigation";
import {
  getDistributions,
  getEarnings,
  markDistributionPaid,
} from "@/services/platform.service";
import type { CommissionDistribution, Earnings } from "@/types/platform";
import { tr } from "@/lib/i18n";

export default function CommissionsPage() {
  const user = useCurrentUser();
  const isAdmin = hasRole(user, "platform-admin");
  const [rows, setRows] = useState<CommissionDistribution[] | null>(null);
  const [earnings, setEarnings] = useState<Earnings | null>(null);

  const [tick, setTick] = useState(0);
  const reload = () => setTick((t) => t + 1);

  useEffect(() => {
    Promise.all([getDistributions(), getEarnings()])
      .then(([list, totals]) => {
        setRows(list.data);
        setEarnings(totals);
      })
      .catch(() => setRows([]));
  }, [tick]);

  return (
    <div>
      <PageHeader title={tr("Commissions")} />

      {earnings && (
        <div className="mb-6 grid grid-cols-3 gap-3">
          {(
            [
              ["Platform", earnings.platform],
              ["Companies", earnings.companies],
              ["Partners", earnings.partners],
            ] as const
          ).map(([label, value]) => (
            <div key={label} className="rounded-lg border p-4">
              <p className="text-sm text-muted-foreground">{label}</p>
              <p className="text-2xl font-bold">{Number(value).toFixed(2)}</p>
            </div>
          ))}
        </div>
      )}

      {rows === null ? (
        <p className="text-muted-foreground">{tr("Loading...")}</p>
      ) : rows.length === 0 ? (
        <EmptyState>{tr("No commission payouts yet.")}</EmptyState>
      ) : (
        <div className="space-y-3">
          {rows.map((d) => (
            <div key={d.id} className="flex items-center justify-between rounded-lg border p-4">
              <div>
                <p className="font-semibold capitalize">{d.type}</p>
                <p className="text-sm text-muted-foreground">{d.amount}</p>
              </div>
              <div className="flex items-center gap-3">
                <Badge variant={d.status === "paid" ? "default" : "secondary"}>{d.status}</Badge>
                {isAdmin && d.status === "pending" && (
                  <Button size="sm" onClick={() => markDistributionPaid(d.id).then(reload)}>
                    {tr("Mark paid")}
                  </Button>
                )}
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
