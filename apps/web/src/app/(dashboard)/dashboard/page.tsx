"use client";

import { useEffect, useState } from "react";

import { useCurrentUser } from "@/components/providers/current-user";
import Link from "next/link";

import { nextSteps } from "@/lib/next-steps";
import { statusLabel } from "@/lib/orders";
import { type DashboardStats, getDashboardStats } from "@/services/platform.service";
import { formatNumber, tr } from "@/lib/i18n";

const money = (n: number) => formatNumber(n, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

/** What to do next, as a short list of links; or a calm "all caught up" when nothing is waiting. */
function NextSteps({ steps }: { steps: ReturnType<typeof nextSteps> }) {
  if (steps.length === 0) {
    return <p className="mt-6 rounded-lg border bg-muted/30 p-4 text-sm text-muted-foreground" role="status">{tr("You are all caught up. Nothing is waiting for you right now.")}</p>;
  }

  return (
    <section className="mt-6" aria-labelledby="next-steps-title">
      <h3 id="next-steps-title" className="mb-2 font-semibold">{tr("Next steps")}</h3>
      <ul className="space-y-2">
        {steps.map((s) => {
          const n = (s as { n?: number }).n;

          return (
            <li key={s.id}>
              <Link href={s.href} className="group flex min-h-14 items-center gap-3 rounded-xl border p-3 transition-colors hover:bg-muted/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-forest">
                <span className="min-w-0 flex-1">
                  <span className="block text-sm font-medium">{tr(s.title, n === undefined ? undefined : { n })}</span>
                  <span className="block text-sm text-muted-foreground">{tr(s.text)}</span>
                </span>
                <span aria-hidden="true" className="text-muted-foreground transition-transform group-hover:translate-x-0.5 rtl:-scale-x-100 rtl:group-hover:-translate-x-0.5">→</span>
              </Link>
            </li>
          );
        })}
      </ul>
    </section>
  );
}

export default function DashboardPage() {
  const user = useCurrentUser();
  const [stats, setStats] = useState<DashboardStats | null>(null);

  useEffect(() => {
    getDashboardStats().then(setStats).catch(() => setStats(null));
  }, []);

  if (!user) return null;

  return (
    <div>
      <h2 className="text-3xl font-bold">{tr("Welcome, {name}", { name: user.name })}</h2>
      <p className="mt-1 text-muted-foreground">
        {user.email}
        {user.industry ? ` · ${tr(user.industry.name)}` : ""}
      </p>

      {!user.industry && !user.roles?.includes("platform-admin") && (
        <p className="mt-4 rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
          {tr("Your account is not linked to an industry yet. Ask your administrator to assign one.")}
        </p>
      )}

      {stats && (
        <>
          <NextSteps steps={nextSteps(user, stats)} />

          <div className="mt-6 grid grid-cols-2 gap-3 md:grid-cols-3">
            {stats.cards.map((c) => (
              <div key={c.key} className="rounded-lg border p-4">
                <p className="text-sm text-muted-foreground">{tr(c.label)}</p>
                <p className="text-2xl font-bold">{c.format === "money" ? money(c.value) : formatNumber(c.value)}</p>
              </div>
            ))}
          </div>

          {Object.keys(stats.orders_by_status).length > 0 && (
            <div className="mt-6">
              <h3 className="mb-2 font-semibold">{tr("Orders by status")}</h3>
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
