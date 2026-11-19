import type { ReactNode } from "react";

const focus = "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900 focus-visible:ring-offset-2";
const svg = { fill: "none", stroke: "currentColor", strokeWidth: 1.75, strokeLinecap: "round", strokeLinejoin: "round", viewBox: "0 0 24 24", "aria-hidden": true } as const;

export const NEXT_GLYPHS = {
  report: <svg {...svg} className="size-5"><path d="M4 20V10M10 20V4M16 20v-7M21 20H3" /></svg>,
  invite: <svg {...svg} className="size-5"><circle cx="9" cy="8.5" r="3" /><path d="M3.5 19c.6-3 2.9-4.7 5.5-4.7s4.9 1.7 5.5 4.7M17 8v6M14 11h6" /></svg>,
  guide: <svg {...svg} className="size-5"><path d="M5 4.500A1.500 1.500 0 0 1 6.500 3H19v15H6.500A1.500 1.500 0 0 0 5 19.500v-15Z" /><path d="M5 19.500A1.500 1.500 0 0 0 6.500 21H19v-3M9 7.500h6" /></svg>,
} satisfies Record<string, ReactNode>;

export interface RecapItem { label: string; value: string }
export interface NextAction { title: string; description: string; href: string; glyph: ReactNode }

/** The last screen of onboarding: what was set up, what to do next, and the way into the product. */
export function SetupCompleteCard({ summary, recap, next, dashboardHref = "#dashboard", callHref = "#book-call" }: {
  summary: string;
  recap: [RecapItem, RecapItem, RecapItem, RecapItem];
  next: [NextAction, NextAction, NextAction];
  dashboardHref?: string;
  callHref?: string;
}) {
  return (
    <div className="grid min-h-screen grid-cols-[minmax(0,1fr)] place-items-center bg-neutral-50 px-4 py-10">
      <section aria-labelledby="complete-title" className="w-full max-w-xl rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-10">
        <div className="flex flex-col items-center text-center">
          {/* A tinted circle, with a lighter ring around it. */}
          <span aria-hidden="true" className="grid size-24 place-items-center rounded-full bg-emerald-50 ring-8 ring-emerald-50/60">
            <span className="grid size-16 place-items-center rounded-full bg-emerald-100 text-emerald-600">
              <svg viewBox="0 0 24 24" className="size-9" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><path d="m5 12.5 4.5 4.5L19 7.5" /></svg>
            </span>
          </span>
          <h1 id="complete-title" className="mt-6 text-2xl font-semibold tracking-tight text-neutral-900">You are all set</h1>
          <p className="mt-2 max-w-sm text-sm leading-relaxed text-neutral-500">{summary}</p>
        </div>

        <ul aria-label="What you set up" className="mt-8 divide-y divide-neutral-100 rounded-xl border border-neutral-200">
          {recap.map((r) => (
            <li key={r.label} className="flex min-h-12 items-center gap-3 px-4 py-2.5">
              <span aria-hidden="true" className="grid size-5 shrink-0 place-items-center rounded-full bg-emerald-100 text-emerald-600">
                <svg viewBox="0 0 24 24" className="size-3" fill="none" stroke="currentColor" strokeWidth="3.5" strokeLinecap="round" strokeLinejoin="round"><path d="m5 12.5 4.5 4.5L19 7.5" /></svg>
              </span>
              <span className="text-sm text-neutral-800">{r.label}</span>
              <span className="ms-auto min-w-0 truncate text-sm text-neutral-500">{r.value}</span>
            </li>
          ))}
        </ul>

        <h2 className="mt-8 text-sm font-semibold text-neutral-900">What next</h2>
        <ul className="mt-3 space-y-2">
          {next.map((n) => (
            <li key={n.title}>
              <a href={n.href} className={`group flex min-h-16 items-center gap-3 rounded-xl border border-neutral-200 bg-white p-3 transition-colors hover:border-neutral-300 hover:bg-neutral-50 ${focus}`}>
                <span aria-hidden="true" className="grid size-10 shrink-0 place-items-center rounded-lg bg-neutral-100 text-neutral-700">{n.glyph}</span>
                <span className="min-w-0 flex-1">
                  <span className="block text-sm font-medium text-neutral-900">{n.title}</span>
                  <span className="block truncate text-sm text-neutral-500">{n.description}</span>
                </span>
                <svg aria-hidden="true" viewBox="0 0 24 24" className="size-4 shrink-0 text-neutral-400 transition-transform group-hover:translate-x-0.5 rtl:-scale-x-100 rtl:group-hover:-translate-x-0.5" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="m9 6 6 6-6 6" /></svg>
              </a>
            </li>
          ))}
        </ul>

        <a href={dashboardHref} className={`mt-8 inline-flex min-h-12 w-full items-center justify-center rounded-xl bg-neutral-900 px-4 text-sm font-semibold text-white transition-colors hover:bg-neutral-800 ${focus}`}>Open dashboard</a>
        <p className="mt-4 text-center text-sm text-neutral-500">
          Want a hand getting started?{" "}
          <a href={callHref} className={`inline-flex min-h-11 items-center rounded font-medium text-neutral-900 underline underline-offset-2 hover:text-neutral-700 ${focus}`}>Book an onboarding call</a>
        </p>
      </section>
    </div>
  );
}
