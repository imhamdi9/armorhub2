"use client";

import { useEffect, useState } from "react";

import { AdminAttention } from "@/components/dashboard/admin-attention";
import { useCurrentUser } from "@/components/providers/current-user";
import Link from "next/link";

import { nextSteps } from "@/lib/next-steps";
import { statusLabel } from "@/lib/orders";
import { type DashboardStats, getDashboardStats, getNotifications } from "@/services/platform.service";
import type { AppNotification } from "@/types/platform";
import { formatDateTime, formatMoney, formatNumber, tr } from "@/lib/i18n";

const money = (n: number) => formatNumber(n, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

/** What to do next, as a short list of links; or a calm "all caught up" when nothing is waiting. */
function NextSteps({ steps }: { steps: ReturnType<typeof nextSteps> }) {
  if (steps.length === 0) {
    return <p className="mt-6 rounded-2xl border bg-white/70 p-5 text-sm text-muted-foreground" role="status">{tr("You are all caught up. Nothing is waiting for you right now.")}</p>;
  }

  return (
    <section className="mt-6 rounded-2xl border bg-white p-5 shadow-card" aria-labelledby="next-steps-title">
      <h3 id="next-steps-title" className="font-display mb-3 text-xl font-semibold">{tr("Next steps")}</h3>
      <ul className="space-y-2">
        {steps.map((s) => {
          const n = (s as { n?: number }).n;

          return (
            <li key={s.id}>
              <Link href={s.href} className="group flex min-h-14 items-center gap-3 rounded-xl border border-transparent bg-bone p-3.5 transition hover:border-border hover:bg-fog focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-forest">
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

function greeting(name: string) {
  const h = new Date().getHours();
  const key = h < 12 ? "Good morning, {name}" : h < 18 ? "Good afternoon, {name}" : "Good evening, {name}";

  return tr(key, { name });
}

type RoleView = { chip: string; actions: [string, string][] };

/** Role label and the two quick actions that matter most to that person. Only existing screens are linked. */
function roleView(user: { roles?: string[]; store_slug?: string | null }): RoleView {
  const has = (...r: string[]) => !!user.roles?.some((x) => r.includes(x));

  if (has("platform-admin")) return { chip: "Platform admin", actions: [["Review what needs attention", "/dashboard/admin"], ["Partner applications", "/dashboard/partner-applications"]] };
  if (has("seller")) return { chip: "Seller", actions: [["Add product", "/dashboard/products"], ["Orders", "/dashboard/seller-orders"]] };
  if (has("partner-manager")) return { chip: "Partner", actions: [["Quote requests", "/dashboard/requests"], ["Jobs", "/dashboard/jobs"]] };
  if (has("technician")) return { chip: "Technician", actions: [["Jobs", "/dashboard/jobs"], ["Installation jobs", "/dashboard/centre-jobs"]] };
  if (has("catalog-reviewer")) return { chip: "Catalogue team", actions: [["Product reviews", "/dashboard/admin/product-reviews"], ["Account", "/dashboard/account"]] };
  if (has("marketing-manager")) return { chip: "Marketing", actions: [["Marketing", "/dashboard/admin/marketing"], ["Account", "/dashboard/account"]] };
  if (has("support-agent")) return { chip: "Support", actions: [["Support", "/dashboard/admin/tools"], ["Orders", "/dashboard/orders"]] };
  if (has("operations-agent")) return { chip: "Operations", actions: [["Business numbers", "/dashboard/admin/kpis"], ["Orders", "/dashboard/orders"]] };
  if (has("warehouse-staff")) return { chip: "Warehouse", actions: [["Warehouse", "/dashboard/warehouse"], ["Orders", "/dashboard/orders"]] };
  if (has("company-owner")) return { chip: "Company owner", actions: [["Orders", "/dashboard/orders"], ["Storefront", "/dashboard/storefront"]] };

  return { chip: "Customer", actions: [["Shop", user.store_slug ? `/s/${user.store_slug}/products` : "/"], ["My orders", "/dashboard/orders"]] };
}

const STAGE_COLORS = ["#1a1a1a", "#FFD21F", "#E0B400", "#F3E39A", "#f3f0e4", "#cfc9ad"];

/** Orders by stage as one stacked bar with a legend, from the live counts. */
function StageBar({ counts }: { counts: Record<string, number> }) {
  const entries = Object.entries(counts).filter(([, n]) => n > 0);
  const total = entries.reduce((sum, [, n]) => sum + n, 0);
  if (total === 0) return null;

  return (
    <section className="rounded-2xl border bg-white p-5 shadow-card" aria-labelledby="stages-title">
      <h3 id="stages-title" className="font-display mb-4 text-xl font-semibold">{tr("Orders by status")}</h3>
      <div className="flex h-4 gap-[3px] overflow-hidden rounded-full" role="img" aria-label={entries.map(([s, n]) => `${statusLabel(s)}: ${n}`).join(", ")}>
        {entries.map(([status, n], i) => <i key={status} style={{ flex: n, backgroundColor: STAGE_COLORS[i % STAGE_COLORS.length] }} />)}
      </div>
      <ul className="mt-4 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
        {entries.map(([status, n], i) => (
          <li key={status} className="flex items-center gap-2">
            <span className="h-2.5 w-2.5 shrink-0 rounded-sm" style={{ backgroundColor: STAGE_COLORS[i % STAGE_COLORS.length] }} aria-hidden="true" />
            <span className="truncate">{statusLabel(status)}</span>
            <strong className="ms-auto font-semibold">{formatNumber(n)}</strong>
          </li>
        ))}
      </ul>
    </section>
  );
}

/** The latest notifications, as a calm activity list. */
function ActivityRail({ items }: { items: AppNotification[] | null }) {
  return (
    <aside className="rounded-2xl border bg-white p-5 shadow-card lg:w-[290px] lg:shrink-0" aria-labelledby="activity-title">
      <div className="mb-1 flex items-center justify-between">
        <h3 id="activity-title" className="font-display text-xl font-semibold">{tr("Recent activity")}</h3>
        <Link href="/dashboard/notifications" className="text-[13px] font-semibold text-[#8a6a00] hover:underline">{tr("See all")}</Link>
      </div>
      {items === null ? (
        <p className="py-6 text-sm text-muted-foreground">{tr("Loading...")}</p>
      ) : items.length === 0 ? (
        <p className="py-6 text-sm text-muted-foreground">{tr("No activity yet.")}</p>
      ) : (
        <ul>
          {items.slice(0, 6).map((n) => (
            <li key={n.id} className="border-t first:border-t-0">
              <Link href={n.path || "/dashboard/notifications"} className="flex items-start gap-3 py-3 text-sm hover:opacity-80">
                <span className={`mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full ${n.read ? "bg-[#F3E39A]" : "bg-[#FFD21F]"}`} aria-hidden="true" />
                <span className="min-w-0 flex-1">
                  <span className="block truncate font-medium">{tr(n.title)}</span>
                  <span className="block text-xs text-muted-foreground">{formatDateTime(n.created_at)}</span>
                </span>
              </Link>
            </li>
          ))}
        </ul>
      )}
    </aside>
  );
}

export default function DashboardPage() {
  const user = useCurrentUser();
  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [activity, setActivity] = useState<AppNotification[] | null>(null);

  useEffect(() => {
    getDashboardStats().then(setStats).catch(() => setStats(null));
    getNotifications().then((r) => setActivity(r.items)).catch(() => setActivity([]));
  }, []);

  if (!user) return null;

  const view = roleView(user);

  return (
    <div>
      <section className="relative overflow-hidden rounded-[30px] bg-[#FFD21F] px-6 pb-24 pt-7 text-[#1a1a1a] sm:px-8">
        <svg className="pointer-events-none absolute inset-0 h-full w-full opacity-[.12]" aria-hidden="true">
          <defs><pattern id="dash-pattern" width="60" height="60" patternUnits="userSpaceOnUse"><path d="M30 0 60 30 30 60 0 30Z" fill="none" stroke="#1a1a1a" strokeWidth="1.5" /><circle cx="30" cy="30" r="8" fill="none" stroke="#1a1a1a" /></pattern></defs>
          <rect width="100%" height="100%" fill="url(#dash-pattern)" />
        </svg>
        <div className="relative flex flex-wrap items-end justify-between gap-5">
          <div>
            <span className="inline-flex rounded-full bg-white/60 px-3.5 py-1.5 text-sm font-semibold">{tr(view.chip)}</span>
            <h2 className="display mt-4 text-[2.3rem] !text-[#1a1a1a] md:text-[2.6rem]">{greeting(user.name)}</h2>
            <p className="mt-2 text-[#1a1a1a]/75">
              {user.email}
              {user.industry ? ` · ${tr(user.industry.name)}` : ""}
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            {view.actions.map(([label, href], i) => (
              <Link key={href} href={href} className={`inline-flex h-10 items-center rounded-full px-5 text-sm font-semibold transition hover:-translate-y-px ${i === 0 ? "bg-[#1a1a1a] text-white" : "bg-white/60 text-[#1a1a1a] hover:bg-white/80"}`}>{tr(label)}</Link>
            ))}
          </div>
        </div>
      </section>

      {!user.industry && !user.roles?.includes("platform-admin") && (
        <p className="mt-4 rounded-2xl border border-dashed bg-white/60 p-4 text-sm text-muted-foreground">
          {tr("Your account is not linked to an industry yet. Ask your administrator to assign one.")}
        </p>
      )}

      {stats && (
        <div className="relative z-10 -mt-14 grid grid-cols-2 gap-4 px-3 sm:px-5 lg:grid-cols-4">
          {stats.cards.map((c, i) => (
            <div key={c.key} className={`rounded-2xl border p-5 shadow-card ${i === 0 ? "border-[#1a1a1a] bg-[#1a1a1a] text-white" : "bg-white"}`}>
              <p className={`text-[13.5px] ${i === 0 ? "text-white/65" : "text-muted-foreground"}`}>{tr(c.label)}</p>
              <p className="font-display mt-1.5 text-[1.9rem] font-bold leading-tight tracking-tight">{c.format === "money" ? (c.currency ? formatMoney(c.value, c.currency) : money(c.value)) : formatNumber(c.value)}</p>
            </div>
          ))}
        </div>
      )}

      <div className={`flex flex-col gap-5 lg:flex-row lg:items-start ${stats ? "mt-5" : "mt-6"}`}>
        <div className="min-w-0 flex-1 space-y-5">
          {user.roles?.includes("platform-admin") && <AdminAttention />}
          {stats && <NextSteps steps={nextSteps(user, stats)} />}
          {stats && <StageBar counts={stats.orders_by_status} />}
        </div>
        <ActivityRail items={activity} />
      </div>
    </div>
  );
}
