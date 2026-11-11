"use client";

import { useEffect, useState } from "react";

import { useCurrentUser } from "@/components/providers/current-user";
import { statusLabel } from "@/lib/orders";
import { type DashboardStats, getDashboardStats } from "@/services/platform.service";

const money = (n: number) => n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export default function DashboardPage() {
  const user = useCurrentUser();
  const [stats, setStats] = useState<DashboardStats | null>(null);

  useEffect(() => {
    getDashboardStats().then(setStats).catch(() => setStats(null));
  }, []);

  if (!user) return null;

  return (
    <div>
      <h2 className="text-3xl font-bold">Welcome, {user.name}</h2>
      <p className="mt-1 text-muted-foreground">
        {user.email}
        {user.industry ? ` · ${user.industry.name}` : ""}
      </p>

      {!user.industry && !user.roles?.includes("platform-admin") && (
        <p className="mt-4 rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
          Your account is not linked to an industry yet. Ask your administrator to assign one.
        </p>
      )}

      {stats && (
        <>
          <div className="mt-6 grid grid-cols-2 gap-3 md:grid-cols-3">
            {stats.cards.map((c) => (
              <div key={c.key} className="rounded-lg border p-4">
                <p className="text-sm text-muted-foreground">{c.label}</p>
                <p className="text-2xl font-bold">{c.format === "money" ? money(c.value) : c.value}</p>
              </div>
            ))}
          </div>

          {Object.keys(stats.orders_by_status).length > 0 && (
            <div className="mt-6">
              <h3 className="mb-2 font-semibold">Orders by status</h3>
              <div className="flex flex-wrap gap-2">
                {Object.entries(stats.orders_by_status).map(([status, n]) => (
                  <span key={status} className="rounded-full border px-3 py-1 text-sm">
                    {statusLabel(status)}: <strong>{n}</strong>
                  </span>
                ))}
              </div>
            </div>
          )}
        </>
      )}
    </div>
  );
}
