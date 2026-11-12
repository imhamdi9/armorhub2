import type { ReactNode } from "react";

const focus = "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900 focus-visible:ring-offset-2";
const svg = { fill: "none", stroke: "currentColor", strokeWidth: 1.75, strokeLinecap: "round", strokeLinejoin: "round", viewBox: "0 0 24 24", "aria-hidden": true } as const;

export const NAV_ICONS = {
  overview: <svg {...svg} className="size-5"><rect x="3.500" y="3.500" width="7" height="9" rx="1.500" /><rect x="13.500" y="3.500" width="7" height="5" rx="1.500" /><rect x="13.500" y="11.500" width="7" height="9" rx="1.500" /><rect x="3.500" y="15.500" width="7" height="5" rx="1.500" /></svg>,
  orders: <svg {...svg} className="size-5"><path d="M6 4h12l1.500 4H4.500L6 4ZM4.500 8v11a1 1 0 0 0 1 1h13a1 1 0 0 0 1-1V8M9.500 12h5" /></svg>,
  customers: <svg {...svg} className="size-5"><circle cx="9" cy="8.500" r="3" /><path d="M3.500 19c.6-3 2.900-4.700 5.500-4.700s4.900 1.700 5.500 4.700M16 5.800a3 3 0 0 1 0 5.400M17.500 14.500c1.700.6 2.700 2.200 3 4.500" /></svg>,
  products: <svg {...svg} className="size-5"><path d="m12 3 8 4.200v9.600L12 21l-8-4.200V7.200L12 3Z" /><path d="m4.500 7.500 7.500 4 7.500-4M12 11.500V21" /></svg>,
  reports: <svg {...svg} className="size-5"><path d="M4 20V10M10 20V4M16 20v-7M21 20H3" /></svg>,
  settings: <svg {...svg} className="size-5"><circle cx="12" cy="12" r="3" /><path d="M12 3v2.500M12 18.500V21M3 12h2.500M18.500 12H21M5.600 5.600l1.800 1.800M16.600 16.600l1.800 1.800M5.600 18.400l1.800-1.800M16.600 7.400l1.800-1.800" /></svg>,
} satisfies Record<string, ReactNode>;

export interface NavItem { label: string; href: string; icon: ReactNode; active?: boolean }
export interface Kpi { label: string; value: string; delta: string; /** Whether the change is good news. */ positive: boolean; direction: "up" | "down"; note?: string }

function Delta({ kpi }: { kpi: Kpi }) {
  return (
    <p className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ${kpi.positive ? "bg-emerald-50 text-emerald-700" : "bg-rose-50 text-rose-700"}`}>
      <svg aria-hidden="true" viewBox="0 0 24 24" className="size-3" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round">
        {kpi.direction === "up" ? <path d="M12 19V5M5.500 11.500 12 5l6.500 6.500" /> : <path d="M12 5v14M5.500 12.500 12 19l6.500-6.500" />}
      </svg>
      <span>{kpi.delta}</span>
      <span className="sr-only">{kpi.positive ? "improvement" : "decline"} {kpi.note ?? "versus last period"}</span>
    </p>
  );
}

export interface DashboardShellProps {
  brand: string;
  nav: NavItem[];
  user: { name: string; email: string };
  title: string;
  action: { label: string; href?: string; onClick?: () => void };
  kpis: [Kpi, Kpi, Kpi, Kpi];
  /** Anything below the KPI row. */
  children?: ReactNode;
}

/** A dashboard frame: fixed sidebar from lg up, a top bar with the page title and one primary action, and a row of KPI cards. */
export function DashboardShell({ brand, nav, user, title, action, kpis, children }: DashboardShellProps) {
  const initials = user.name.split(/\s+/).map((w) => w[0]).slice(0, 2).join("").toUpperCase();
  const actionClass = `inline-flex min-h-11 items-center justify-center rounded-xl bg-neutral-900 px-4 text-sm font-semibold text-white transition-colors hover:bg-neutral-800 ${focus}`;

  return (
    <div className="min-h-screen bg-neutral-50">
      <aside className="fixed inset-y-0 start-0 z-30 hidden w-64 flex-col border-e border-neutral-200 bg-white lg:flex" aria-label="Sidebar">
        <div className="flex h-16 shrink-0 items-center gap-2.5 px-5">
          <span aria-hidden="true" className="grid size-8 place-items-center rounded-lg bg-neutral-900 text-sm font-bold text-white">{brand[0]}</span>
          <span className="text-base font-semibold tracking-tight text-neutral-900">{brand}</span>
        </div>

        <nav aria-label="Main" className="flex-1 overflow-y-auto px-3 py-2">
          <ul className="space-y-1">
            {nav.map((item) => (
              <li key={item.label}>
                <a
                  href={item.href}
                  aria-current={item.active ? "page" : undefined}
                  className={`flex min-h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium transition-colors ${item.active ? "bg-neutral-100 text-neutral-900" : "text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900"} ${focus}`}
                >
                  <span className={item.active ? "text-neutral-900" : "text-neutral-400"}>{item.icon}</span>
                  {item.label}
                </a>
              </li>
            ))}
          </ul>
        </nav>

        <div className="border-t border-neutral-200 p-3">
          <button type="button" className={`flex min-h-14 w-full items-center gap-3 rounded-xl px-2 py-2 text-start transition-colors hover:bg-neutral-50 ${focus}`} aria-label={`Account menu for ${user.name}`}>
            <span aria-hidden="true" className="grid size-9 shrink-0 place-items-center rounded-full bg-amber-200 text-sm font-semibold text-amber-900">{initials}</span>
            <span className="min-w-0 flex-1">
              <span className="block truncate text-sm font-medium text-neutral-900">{user.name}</span>
              <span className="block truncate text-xs text-neutral-500" dir="ltr">{user.email}</span>
            </span>
          </button>
        </div>
      </aside>

      <div className="lg:ps-64">
        <header className="sticky top-0 z-20 flex min-h-16 items-center justify-between gap-4 border-b border-neutral-200 bg-white/90 px-4 backdrop-blur sm:px-6 lg:px-8">
          <div className="flex min-w-0 items-center gap-3">
            <span aria-hidden="true" className="grid size-8 shrink-0 place-items-center rounded-lg bg-neutral-900 text-sm font-bold text-white lg:hidden">{brand[0]}</span>
            <h1 className="truncate text-lg font-semibold tracking-tight text-neutral-900">{title}</h1>
          </div>
          {action.href ? <a href={action.href} className={actionClass}>{action.label}</a> : <button type="button" onClick={action.onClick} className={actionClass}>{action.label}</button>}
        </header>

        <main className="px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
          <section aria-label="Key figures" className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            {kpis.map((k) => (
              <article key={k.label} className="rounded-2xl border border-neutral-200 bg-white p-5">
                <p className="text-sm text-neutral-500">{k.label}</p>
                <p className="mt-2 text-3xl font-semibold tracking-tight text-neutral-900 tabular-nums">{k.value}</p>
                <div className="mt-3 flex items-center gap-2">
                  <Delta kpi={k} />
                  <span className="text-xs text-neutral-400" aria-hidden="true">{k.note ?? "vs last month"}</span>
                </div>
              </article>
            ))}
          </section>
          {children}
        </main>
      </div>
    </div>
  );
}
